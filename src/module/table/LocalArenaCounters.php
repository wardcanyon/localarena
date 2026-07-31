<?php

// Counters: the framework components that store, bound, and publish
// the plain integers a game keeps track of -- a player's money, the
// current round, and so on.
//
// See https://en.doc.boardgamearena.com/PlayerCounter_and_TableCounter
//
// A game creates its counters in its Game class's constructor, using
// the factory that the framework provides as `$this->counterFactory`:
//
//     $this->roundCounter = $this->counterFactory->createTableCounter('round');
//     $this->playerCredits = $this->counterFactory->createPlayerCounter('credits');
//
// and creates their storage during `setupNewGame()`:
//
//     $this->roundCounter->initDb();
//     $this->playerCredits->initDb(array_keys($players));
//
// A `TableCounter` holds one value for the whole table; a
// `PlayerCounter` holds one value per player.  Values live in the
// per-table database, in `bga_table_counters` and
// `bga_player_counters` (created by `initDb()`), except for the two
// counters that every game has by default -- `$this->playerScore` and
// `$this->playerScoreAux` -- which are stored in the `player_score`
// and `player_score_aux` columns of the `player` table and therefore
// need no `initDb()` call.
//
// Every `set()`/`inc()`/`setAll()` also sends the front end a
// notification (`setPlayerCounter`, `setTableCounter`, or
// `setPlayerCounterAll`), which is what lets an `ebg.counter` created
// with a `playerCounter`/`tableCounter` option update itself; see
// `src/ebg/counter.ts`.
//
// LocalArena divergences (all deliberate; a game that trips one of
// these is doing something a BGA table would let it get away with,
// but that a test is better off being told about):
//
//   - Reading a counter whose `initDb()` was never called is an
//     error, rather than a silent zero.
//
//   - Creating two counters with the same name is an error: their
//     notifications -- and, for two player counters or two table
//     counters, their rows -- would be indistinguishable.
//
//   - A counter whose visibility is not `VISIBLE` does not send its
//     value to the players who may not see it (rather than sending a
//     redacted notification), so those players' game logs do not carry
//     the message that accompanied the update.

namespace Bga\GameFramework\Components\Counters;

require_once APP_GAMEMODULE_PATH . 'module/table/feException.php';
require_once APP_GAMEMODULE_PATH . 'module/table/BgaVisibleSystemException.php';
require_once APP_GAMEMODULE_PATH . 'module/table/NotificationMessage.php';

use Bga\GameFramework\NotificationMessage;

// Who may see a player counter's values.
//
// The BGA documentation names these "public", "self", and "private";
// the enum cases are named after the `isVisible()`/`isSelf()`/
// `isHidden()` predicates that report them.
enum CounterVisibility
{
  // Everyone can see every player's value.  (BGA: "public".)
  case VISIBLE;

  // Each player can see only their own value; other players' counters
  // display "-".  (BGA: "self".)
  case SELF;

  // Nobody can see the values; every counter displays "-".  (BGA:
  // "private".)  The values are still kept server-side.
  case HIDDEN;
}

// Thrown when a `set()`/`inc()` would take a counter outside the
// [min, max] range it was created with.
class OutOfRangeCounterException extends \BgaVisibleSystemException
{
}

// Thrown when a player counter is asked about a player it does not
// have a value for -- one that was not passed to `initDb()` -- or,
// for a "strict" counter, one that is not at the table at all.
class UnknownPlayerException extends \BgaVisibleSystemException
{
}

// The behavior shared by `TableCounter` and `PlayerCounter`: the
// counter's name, its bounds, and the arguments its notifications
// carry.
abstract class Counter
{
  // Counter names appear in a `varchar(64)` key column and in
  // notification arguments; this is what we accept.
  const NAME_PATTERN = '/^[A-Za-z0-9_\-]{1,64}$/';

  protected \Table $table_;
  protected string $name_;
  protected ?int $min_;
  protected ?int $max_;

  public function __construct(\Table $table, string $name, ?int $min, ?int $max)
  {
    if (!preg_match(self::NAME_PATTERN, $name)) {
      throw new \BgaVisibleSystemException(
        'Invalid counter name "' .
          $name .
          '": names may be up to 64 characters of letters, numbers, underscores, and hyphens.'
      );
    }
    if ($min !== null && $max !== null && $min > $max) {
      throw new \BgaVisibleSystemException(
        'Counter "' . $name . '" was created with a minimum (' . $min . ') above its maximum (' . $max . ').'
      );
    }

    $this->table_ = $table;
    $this->name_ = $name;
    $this->min_ = $min;
    $this->max_ = $max;
  }

  // XXX: This is part of the LocalArena API, not the BGA API.  (BGA
  // does not document an accessor for a counter's own name; test
  // fixtures want one.)
  public function getName(): string
  {
    return $this->name_;
  }

  // Returns the lowest value the counter may take, or null if it is
  // unbounded below.
  public function getMin(): ?int
  {
    return $this->min_;
  }

  // Returns the highest value the counter may take, or null if it is
  // unbounded above.
  public function getMax(): ?int
  {
    return $this->max_;
  }

  protected function checkRange(int $value): void
  {
    if (($this->min_ !== null && $value < $this->min_) || ($this->max_ !== null && $value > $this->max_)) {
      throw new OutOfRangeCounterException(
        'Counter "' .
          $this->name_ .
          '" cannot take the value ' .
          $value .
          ': its range is ' .
          ($this->min_ === null ? '(unbounded)' : strval($this->min_)) .
          ' to ' .
          ($this->max_ === null ? '(unbounded)' : strval($this->max_)) .
          '.'
      );
    }
  }

  // The notification arguments that describe an update, before the
  // message's own arguments are folded in.
  protected function updateArgs(int $value, int $old_value): array
  {
    return [
      'name' => $this->name_,
      'value' => $value,
      'oldValue' => $old_value,
      'inc' => $value - $old_value,
      'absInc' => abs($value - $old_value),
    ];
  }

  // Combines a message's arguments with the ones the counter supplies
  // itself.  The counter's own arguments win: they describe the
  // update that is being announced.
  protected function notifArgs(?NotificationMessage $message, array $counter_args): array
  {
    return array_merge($message === null ? [] : $message->args, $counter_args);
  }

  // True if `$table_name` exists in the table's database.
  protected function dbTableExists(string $table_name): bool
  {
    return count($this->table_->getObjectListFromDB("SHOW TABLES LIKE '" . $table_name . "'")) > 0;
  }

  protected function escapedName(): string
  {
    return $this->table_->escapeStringForDB($this->name_);
  }
}

// A counter with a single value for the whole table (the current
// round, the number of tokens left in the bag, ...).
class TableCounter extends Counter
{
  const DB_TABLE = 'bga_table_counters';

  // Creates the counters table if it does not exist yet, and gives
  // this counter its starting value.  Must be called from
  // `setupNewGame()` (or, when adding a counter to a game that is
  // already published, from `upgradeTableDb()`).
  //
  // Calling this again for a counter that already has a value leaves
  // that value alone, so that an `upgradeTableDb()` that adds a
  // counter can be run against tables that already have one.
  public function initDb(int $initialValue = 0): void
  {
    $this->checkRange($initialValue);

    $this->table_->DbQuery(
      'CREATE TABLE IF NOT EXISTS `' .
        self::DB_TABLE .
        '` (' .
        '`counter_name` varchar(64) NOT NULL,' .
        '`counter_value` int(11) NOT NULL,' .
        'PRIMARY KEY (`counter_name`)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $this->table_->DbQuery(
      'INSERT IGNORE INTO `' .
        self::DB_TABLE .
        "` (`counter_name`, `counter_value`) VALUES ('" .
        $this->escapedName() .
        "'," .
        $initialValue .
        ')'
    );
  }

  // Returns the counter's current value.
  public function get(): int
  {
    $rows = $this->dbTableExists(self::DB_TABLE)
      ? $this->table_->getObjectListFromDB(
        'SELECT `counter_value` FROM `' . self::DB_TABLE . "` WHERE `counter_name` = '" . $this->escapedName() . "'",
        /*bUniqueValue=*/ true
      )
      : [];

    if (count($rows) === 0) {
      throw new \BgaVisibleSystemException(
        'Table counter "' .
          $this->name_ .
          '" has no value: initDb() must be called for it from setupNewGame() (or, for a game that is already ' .
          'published, from upgradeTableDb()).'
      );
    }
    return intval($rows[0]);
  }

  // Sets the counter's value, announces it to the front end, and
  // returns the new value.
  public function set(int $value, ?NotificationMessage $message = new NotificationMessage()): int
  {
    $old_value = $this->get();
    $this->checkRange($value);
    $this->write($value);
    $this->notify($value, $old_value, $message);
    return $value;
  }

  // Adds `$inc` to the counter's value, announces it to the front
  // end, and returns the new value.  An increment of 0 changes
  // nothing and sends no notification.
  public function inc(int $inc, ?NotificationMessage $message = new NotificationMessage()): int
  {
    $old_value = $this->get();
    if ($inc === 0) {
      return $old_value;
    }
    $value = $old_value + $inc;
    $this->checkRange($value);
    $this->write($value);
    $this->notify($value, $old_value, $message);
    return $value;
  }

  // Sets the counter's value in `$result` (the array that
  // `getAllDatas()` returns), under `$fieldName` if given and the
  // counter's own name otherwise.
  public function fillResult(array &$result, ?string $fieldName = null): void
  {
    $result[$fieldName ?? $this->name_] = $this->get();
  }

  private function write(int $value): void
  {
    $this->table_->DbQuery(
      'UPDATE `' .
        self::DB_TABLE .
        '` SET `counter_value` = ' .
        $value .
        " WHERE `counter_name` = '" .
        $this->escapedName() .
        "'"
    );
  }

  private function notify(int $value, int $old_value, ?NotificationMessage $message): void
  {
    if ($message === null) {
      return;
    }
    $this->table_->notifyAllPlayers(
      'setTableCounter',
      $message->message,
      $this->notifArgs($message, $this->updateArgs($value, $old_value))
    );
  }
}

// A counter with one value per player (a player's money, their
// remaining actions, ...).
class PlayerCounter extends Counter
{
  private CounterVisibility $visibility_;
  private bool $useNo_;
  private bool $strict_;
  private LocalArenaPlayerCounterStore $store_;

  // XXX: This is part of the LocalArena API, not the BGA API; games
  // create player counters through `$this->counterFactory`.
  public function __construct(
    \Table $table,
    string $name,
    ?int $min,
    ?int $max,
    CounterVisibility $visibility,
    bool $useNo,
    bool $strict,
    LocalArenaPlayerCounterStore $store
  ) {
    parent::__construct($table, $name, $min, $max);
    $this->visibility_ = $visibility;
    $this->useNo_ = $useNo;
    $this->strict_ = $strict;
    $this->store_ = $store;
  }

  // Creates the counters table if it does not exist yet, and gives
  // this counter a starting value for each of `$playerIdsOrNos`
  // (usually `array_keys($players)`, but a game may add e.g. 0 for an
  // automaton).  Must be called from `setupNewGame()` (or, when
  // adding a counter to a game that is already published, from
  // `upgradeTableDb()`).
  //
  // Players who already have a value keep it, so that an
  // `upgradeTableDb()` that adds a counter can be run against tables
  // that already have one.
  public function initDb(array $playerIdsOrNos, int $initialValue = 0): void
  {
    $this->checkRange($initialValue);
    $this->store_->initDb(array_map('intval', array_values($playerIdsOrNos)), $initialValue);
  }

  // Returns the counter's current value for the given player.
  public function get(int $playerIdOrNo): int
  {
    $this->checkStrict($playerIdOrNo);
    $value = $this->store_->get($playerIdOrNo);
    if ($value === null) {
      throw new UnknownPlayerException($this->unknownPlayerMessage($playerIdOrNo));
    }
    return $value;
  }

  // Sets the counter's value for the given player, announces it to
  // the front end, and returns the new value.
  public function set(int $playerIdOrNo, int $value, ?NotificationMessage $message = new NotificationMessage()): int
  {
    $old_value = $this->get($playerIdOrNo);
    $this->checkRange($value);
    $this->store_->set($playerIdOrNo, $value);
    $this->notify($playerIdOrNo, $value, $old_value, $message);
    return $value;
  }

  // Adds `$inc` to the counter's value for the given player,
  // announces it to the front end, and returns the new value.  An
  // increment of 0 changes nothing and sends no notification.
  public function inc(int $playerIdOrNo, int $inc, ?NotificationMessage $message = new NotificationMessage()): int
  {
    $old_value = $this->get($playerIdOrNo);
    if ($inc === 0) {
      return $old_value;
    }
    $value = $old_value + $inc;
    $this->checkRange($value);
    $this->store_->set($playerIdOrNo, $value);
    $this->notify($playerIdOrNo, $value, $old_value, $message);
    return $value;
  }

  // Returns every player's value, as an array mapping player id (or
  // player no, for a "useNo" counter) to value.
  public function getAll(): array
  {
    return $this->store_->getAll();
  }

  // Sets the counter's value for every player it has a value for,
  // announces it to the front end, and returns the new value.
  public function setAll(int $value, ?NotificationMessage $message = new NotificationMessage()): int
  {
    $this->checkRange($value);
    $this->store_->setAll($value);
    $this->notifyAll($value, $message);
    return $value;
  }

  // Sets each player's value on their sub-array of
  // `$result['players']` (the array that `getAllDatas()` returns),
  // under `$fieldName` if given and the counter's own name otherwise.
  //
  // Values the viewer may not see are filled in as null, which is
  // what makes the front-end counter display "-":
  //
  //   - a "self" counter shows only `$currentPlayerId`'s own value
  //     (and, with no `$currentPlayerId`, nothing at all);
  //   - a "private" counter shows nothing.
  public function fillResult(array &$result, ?string $fieldName = null, ?int $currentPlayerId = null): void
  {
    if (!isset($result['players']) || !is_array($result['players'])) {
      throw new \BgaVisibleSystemException(
        'PlayerCounter::fillResult() for counter "' .
          $this->name_ .
          '" needs $result["players"] to be an array of per-player data.'
      );
    }

    $field = $fieldName ?? $this->name_;
    $values = $this->valuesByPlayerId();

    foreach ($result['players'] as $player_id => &$player_data) {
      if (!is_array($player_data)) {
        continue;
      }
      $value = $values[intval($player_id)] ?? null;
      $player_data[$field] = $this->isValueVisibleTo(intval($player_id), $currentPlayerId) ? $value : null;
    }
    // `foreach` by reference leaves $player_data aliasing the last
    // element; break that alias before it can bite a later write.
    unset($player_data);
  }

  // ---- Visibility ----

  public function setVisibility(CounterVisibility $visibility): void
  {
    $this->visibility_ = $visibility;
  }

  // XXX: This is part of the LocalArena API, not the BGA API.
  public function getVisibility(): CounterVisibility
  {
    return $this->visibility_;
  }

  // True if everyone can see every player's value.
  public function isVisible(): bool
  {
    return $this->visibility_ === CounterVisibility::VISIBLE;
  }

  // True if nobody can see the values.
  public function isHidden(): bool
  {
    return $this->visibility_ === CounterVisibility::HIDDEN;
  }

  // True if each player can see only their own value.
  public function isSelf(): bool
  {
    return $this->visibility_ === CounterVisibility::SELF;
  }

  // ---- Player identification ----

  // Chooses whether this counter's methods take player nos (true) or
  // player ids (false).
  //
  // Note that this does not rewrite anything already stored: the keys
  // written by `initDb()` are whatever was passed to it.
  public function setUseNo(bool $useNo): void
  {
    $this->useNo_ = $useNo;
    $this->store_->setUseNo($useNo);
  }

  // XXX: This is part of the LocalArena API, not the BGA API.
  public function getUseNo(): bool
  {
    return $this->useNo_;
  }

  // Chooses whether the player id (or no) passed to this counter's
  // methods is validated against the players at the table.
  public function setStrict(bool $strict): void
  {
    $this->strict_ = $strict;
  }

  // XXX: This is part of the LocalArena API, not the BGA API.
  public function getStrict(): bool
  {
    return $this->strict_;
  }

  // ---- Internals ----

  // For a strict counter, rejects a player id (or no) that is not at
  // the table.  Note that a counter may legitimately hold values for
  // keys that are not players -- 0 for an automaton, say -- which is
  // exactly what such a counter turns strictness off for.
  private function checkStrict(int $playerIdOrNo): void
  {
    if (!$this->strict_) {
      return;
    }
    if ($this->playerRow($playerIdOrNo) === null) {
      throw new UnknownPlayerException(
        'Counter "' .
          $this->name_ .
          '" is strict, and there is no player with ' .
          ($this->useNo_ ? 'player no ' : 'player id ') .
          $playerIdOrNo .
          ' at this table.'
      );
    }
  }

  private function unknownPlayerMessage(int $playerIdOrNo): string
  {
    return 'Counter "' .
      $this->name_ .
      '" has no value for ' .
      ($this->useNo_ ? 'player no ' : 'player id ') .
      $playerIdOrNo .
      ': it was not among the players passed to initDb().';
  }

  // The `player` row for a player id (or no), or null if there is no
  // such player at the table.
  private function playerRow(int $playerIdOrNo)
  {
    $row = $this->table_->getObjectFromDB(
      'SELECT * FROM `player` WHERE `' . ($this->useNo_ ? 'player_no' : 'player_id') . '` = ' . $playerIdOrNo
    );
    return $row === null || count($row) === 0 ? null : $row;
  }

  // This counter's values, re-keyed by player id even when the
  // counter itself is keyed by player no.  Keys that do not belong to
  // a player at the table (an automaton's, say) are dropped.
  private function valuesByPlayerId(): array
  {
    $values = $this->getAll();
    if (!$this->useNo_) {
      return $values;
    }

    $player_ids_by_no = $this->table_->getCollectionFromDB(
      'SELECT `player_no`, `player_id` FROM `player`',
      /*bSingleValue=*/ true
    );

    $by_id = [];
    foreach ($values as $no => $value) {
      if (isset($player_ids_by_no[$no])) {
        $by_id[intval($player_ids_by_no[$no])] = $value;
      }
    }
    return $by_id;
  }

  // Whether `$viewer_id` (null for "no particular player", e.g. a
  // notification sent to everyone) may see `$player_id`'s value.
  private function isValueVisibleTo(int $player_id, ?int $viewer_id): bool
  {
    return match ($this->visibility_) {
      CounterVisibility::VISIBLE => true,
      CounterVisibility::SELF => $viewer_id !== null && $viewer_id === $player_id,
      CounterVisibility::HIDDEN => false,
    };
  }

  // Announces an update of one player's value to the players who may
  // see it: everyone for a "public" counter, the owning player alone
  // for a "self" counter, nobody for a "private" one.
  private function notify(int $playerIdOrNo, int $value, int $old_value, ?NotificationMessage $message): void
  {
    if ($message === null || $this->isHidden()) {
      return;
    }

    $row = $this->playerRow($playerIdOrNo);
    $counter_args = $this->updateArgs($value, $old_value);
    // The front end matches counters on whatever this counter is
    // keyed by, so this carries the caller's key (see `useNo`).
    $counter_args['playerId'] = $playerIdOrNo;
    if ($row !== null) {
      $counter_args['player_name'] = $row['player_name'];
    }
    $args = $this->notifArgs($message, $counter_args);

    if ($this->isSelf()) {
      if ($row !== null) {
        $this->table_->notifyPlayer(intval($row['player_id']), 'setPlayerCounter', $message->message, $args);
      }
      return;
    }
    $this->table_->notifyAllPlayers('setPlayerCounter', $message->message, $args);
  }

  // Announces a `setAll()`.  Since every player's value is the same,
  // there is no per-player `oldValue`/`inc` to report.
  private function notifyAll(int $value, ?NotificationMessage $message): void
  {
    if ($message === null || $this->isHidden()) {
      return;
    }

    $args = $this->notifArgs($message, ['name' => $this->name_, 'value' => $value]);

    if ($this->isSelf()) {
      foreach (array_keys($this->table_->rawGetPlayers()) as $player_id) {
        $this->table_->notifyPlayer(intval($player_id), 'setPlayerCounterAll', $message->message, $args);
      }
      return;
    }
    $this->table_->notifyAllPlayers('setPlayerCounterAll', $message->message, $args);
  }
}

// Where a player counter's values live.
//
// XXX: This is part of the LocalArena API, not the BGA API.  Ordinary
// counters are stored in `bga_player_counters`
// (`LocalArenaPlayerCounterTableStore`); the two counters that every
// game has by default are stored in the `player` table
// (`LocalArenaPlayerColumnStore`), which is why they need no
// `initDb()`.
abstract class LocalArenaPlayerCounterStore
{
  protected \Table $table_;
  protected bool $useNo_;

  public function __construct(\Table $table, bool $useNo)
  {
    $this->table_ = $table;
    $this->useNo_ = $useNo;
  }

  public function setUseNo(bool $useNo): void
  {
    $this->useNo_ = $useNo;
  }

  // Creates whatever storage is needed and gives each of `$keys` the
  // starting value, leaving any existing value alone.
  abstract public function initDb(array $keys, int $initial_value): void;

  // The value stored for `$key`, or null if there is none.
  abstract public function get(int $key): ?int;

  // Every stored value, as key => value.
  abstract public function getAll(): array;

  abstract public function set(int $key, int $value): void;

  abstract public function setAll(int $value): void;
}

// The default storage: one row per (counter, player) in
// `bga_player_counters`.
class LocalArenaPlayerCounterTableStore extends LocalArenaPlayerCounterStore
{
  const DB_TABLE = 'bga_player_counters';

  private string $name_;

  public function __construct(\Table $table, string $name, bool $useNo)
  {
    parent::__construct($table, $useNo);
    $this->name_ = $name;
  }

  public function initDb(array $keys, int $initial_value): void
  {
    $this->table_->DbQuery(
      'CREATE TABLE IF NOT EXISTS `' .
        self::DB_TABLE .
        '` (' .
        '`counter_name` varchar(64) NOT NULL,' .
        // The player id, or the player no for a "useNo" counter; a
        // game may also use a key that is neither (0 for an
        // automaton, say).
        '`player_id` int(11) NOT NULL,' .
        '`counter_value` int(11) NOT NULL,' .
        'PRIMARY KEY (`counter_name`, `player_id`)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    if (count($keys) === 0) {
      return;
    }

    $values = [];
    foreach ($keys as $key) {
      $values[] = "('" . $this->escapedName() . "'," . $key . ',' . $initial_value . ')';
    }
    $this->table_->DbQuery(
      'INSERT IGNORE INTO `' .
        self::DB_TABLE .
        '` (`counter_name`, `player_id`, `counter_value`) VALUES ' .
        implode(',', $values)
    );
  }

  public function get(int $key): ?int
  {
    if (!$this->tableExists()) {
      return null;
    }
    $rows = $this->table_->getObjectListFromDB(
      'SELECT `counter_value` FROM `' .
        self::DB_TABLE .
        "` WHERE `counter_name` = '" .
        $this->escapedName() .
        "' AND `player_id` = " .
        $key,
      /*bUniqueValue=*/ true
    );
    return count($rows) === 0 ? null : intval($rows[0]);
  }

  public function getAll(): array
  {
    if (!$this->tableExists()) {
      return [];
    }
    $rows = $this->table_->getCollectionFromDB(
      'SELECT `player_id`, `counter_value` FROM `' .
        self::DB_TABLE .
        "` WHERE `counter_name` = '" .
        $this->escapedName() .
        "' ORDER BY `player_id` ASC",
      /*bSingleValue=*/ true
    );

    $values = [];
    foreach ($rows as $key => $value) {
      $values[intval($key)] = intval($value);
    }
    return $values;
  }

  public function set(int $key, int $value): void
  {
    $this->table_->DbQuery(
      'UPDATE `' .
        self::DB_TABLE .
        '` SET `counter_value` = ' .
        $value .
        " WHERE `counter_name` = '" .
        $this->escapedName() .
        "' AND `player_id` = " .
        $key
    );
  }

  public function setAll(int $value): void
  {
    if (!$this->tableExists()) {
      return;
    }
    $this->table_->DbQuery(
      'UPDATE `' .
        self::DB_TABLE .
        '` SET `counter_value` = ' .
        $value .
        " WHERE `counter_name` = '" .
        $this->escapedName() .
        "'"
    );
  }

  private function tableExists(): bool
  {
    return count($this->table_->getObjectListFromDB("SHOW TABLES LIKE '" . self::DB_TABLE . "'")) > 0;
  }

  private function escapedName(): string
  {
    return $this->table_->escapeStringForDB($this->name_);
  }
}

// The storage behind `$this->playerScore` and
// `$this->playerScoreAux`: a column of the `player` table, so that
// updating the counter updates the score BGA itself reads, and so
// that the counter needs no `initDb()` -- every player at the table
// has a value from the moment they are seated.
class LocalArenaPlayerColumnStore extends LocalArenaPlayerCounterStore
{
  private string $column_;

  public function __construct(\Table $table, string $column, bool $useNo)
  {
    parent::__construct($table, $useNo);
    $this->column_ = $column;
  }

  public function initDb(array $keys, int $initial_value): void
  {
    // The rows already exist (they are the players); all that is left
    // is the starting value.
    foreach ($keys as $key) {
      $this->set($key, $initial_value);
    }
  }

  public function get(int $key): ?int
  {
    $rows = $this->table_->getObjectListFromDB(
      'SELECT `' . $this->column_ . '` FROM `player` WHERE `' . $this->keyColumn() . '` = ' . $key,
      /*bUniqueValue=*/ true
    );
    return count($rows) === 0 ? null : intval($rows[0]);
  }

  public function getAll(): array
  {
    $rows = $this->table_->getCollectionFromDB(
      'SELECT `' . $this->keyColumn() . '`, `' . $this->column_ . '` FROM `player` ORDER BY `player_no` ASC',
      /*bSingleValue=*/ true
    );

    $values = [];
    foreach ($rows as $key => $value) {
      $values[intval($key)] = intval($value);
    }
    return $values;
  }

  public function set(int $key, int $value): void
  {
    $this->table_->DbQuery(
      'UPDATE `player` SET `' . $this->column_ . '` = ' . $value . ' WHERE `' . $this->keyColumn() . '` = ' . $key
    );
  }

  public function setAll(int $value): void
  {
    $this->table_->DbQuery('UPDATE `player` SET `' . $this->column_ . '` = ' . $value);
  }

  private function keyColumn(): string
  {
    return $this->useNo_ ? 'player_no' : 'player_id';
  }
}

// The factory that games create their counters with, available as
// `$this->counterFactory`.
class CounterFactory
{
  private \Table $table_;

  // Every counter this factory has created, by name; see
  // `localarenaGetCounter()`.
  private array $counters_ = [];

  public function __construct(\Table $table)
  {
    $this->table_ = $table;
  }

  // Creates a counter with one value per player.
  //
  // `$min`/`$max` bound the values it may take (null for unbounded);
  // `$visibility` says who may see them; `$useNo` makes the counter's
  // methods take player nos rather than player ids; and `$strict`
  // makes them reject a player who is not at the table.
  //
  // `$strict` defaults to null, meaning "let the framework decide".
  // LocalArena, being a development and testing environment (like BGA
  // Studio, and unlike production), decides to validate.  A counter
  // that deliberately holds values for keys that are not players --
  // an automaton's, say -- should pass `strict: false`.
  public function createPlayerCounter(
    string $name,
    ?int $min = 0,
    ?int $max = null,
    CounterVisibility $visibility = CounterVisibility::VISIBLE,
    bool $useNo = false,
    ?bool $strict = null
  ): PlayerCounter {
    $counter = new PlayerCounter(
      $this->table_,
      $name,
      $min,
      $max,
      $visibility,
      $useNo,
      $strict ?? true,
      new LocalArenaPlayerCounterTableStore($this->table_, $name, $useNo)
    );
    $this->register($counter);
    return $counter;
  }

  // Creates a counter with a single value for the whole table.
  public function createTableCounter(string $name, ?int $min = 0, ?int $max = null): TableCounter
  {
    $counter = new TableCounter($this->table_, $name, $min, $max);
    $this->register($counter);
    return $counter;
  }

  // Creates one of the counters that every game has by default:
  // player counters stored in a column of the `player` table, so that
  // they need no `initDb()` and so that setting them keeps the score
  // BGA reads up to date.
  //
  // XXX: This is part of the LocalArena API, not the BGA API; it is
  // intended only for internal use (by `Table`).
  public function localarenaCreatePlayerColumnCounter(string $name, string $column): PlayerCounter
  {
    $counter = new PlayerCounter(
      $this->table_,
      $name,
      // The default score counters are unbounded, and are not strict
      // (so that a game may keep a score for e.g. an automaton
      // without tripping validation).
      /*min=*/ null,
      /*max=*/ null,
      CounterVisibility::VISIBLE,
      /*useNo=*/ false,
      /*strict=*/ false,
      new LocalArenaPlayerColumnStore($this->table_, $column, /*useNo=*/ false)
    );
    $this->register($counter);
    return $counter;
  }

  // Returns the counter with the given name.
  //
  // XXX: This is part of the LocalArena API, not the BGA API; games
  // hold on to the counters they create (test fixtures cannot).
  public function localarenaGetCounter(string $name): Counter
  {
    if (!array_key_exists($name, $this->counters_)) {
      throw new \BgaVisibleSystemException(
        'This game has no counter named "' .
          $name .
          '".  (Counters: ' .
          (count($this->counters_) === 0 ? 'none' : implode(', ', array_keys($this->counters_))) .
          '.)'
      );
    }
    return $this->counters_[$name];
  }

  // Returns every counter this game has created, by name.
  //
  // XXX: This is part of the LocalArena API, not the BGA API.
  public function localarenaGetCounters(): array
  {
    return $this->counters_;
  }

  private function register(Counter $counter): void
  {
    $name = $counter->getName();
    if (array_key_exists($name, $this->counters_)) {
      throw new \BgaVisibleSystemException(
        'This game has already created a counter named "' .
          $name .
          '".  Counter names identify a counter to the front end (and, for player and table counters alike, in the ' .
          'database), so they must be unique.'
      );
    }
    $this->counters_[$name] = $counter;
  }
}
