<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once '/src/localarena/module/tablemanager/TableParams.php';

define('DEV_MODE', 1);

// These need to be set so that games can include
// APP_GAMEMODULE_PATH.'module/table/table.game.php' and
// APP_BASE_PATH.''view/common/game.view.php'.
define('APP_BASE_PATH', '/src/localarena/');
define('APP_GAMEMODULE_PATH', '/src/localarena/');

// Each game should be in a subdirectory of this one.
define('LOCALARENA_GAME_PATH', '/src/game/');

use \LocalArena\TableParams;
use \Bga\GameFramework\Components\Counters\Counter;
use \Bga\GameFramework\Components\Counters\PlayerCounter;
use \Bga\GameFramework\Components\Counters\TableCounter;

// The game-specific view code expects this.
//
// XXX: Find this a better home; also, this is duplicated from
// "index.php".  We almost certainly eventually need to be able to
// manipulate this on a per-statement basis.
$currentPlayer = 12345;
class GUser
{
  public int $id;

  public function __construct($id)
  {
    $this->id = $id;
  }

  public function get_id()
  {
    return $this->id;
  }
}
global $g_user;
$g_user = new GUser($currentPlayer);

require_once APP_GAMEMODULE_PATH . 'module/table/table.game.php';
require_once APP_GAMEMODULE_PATH . 'module/tablemanager/tablemanager.php';

class IntegrationTestCase extends \PHPUnit\Framework\TestCase
{
  private $table_ = null;

  protected function setUp(): void
  {
  }

  protected function tearDown(): void
  {
    $this->table()->closeDbConnection();
  }

    public function onNotSuccessfulTest(\Throwable $exc): never {
        // XXX: This does work, but it doesn't leave a nice message at
        // the bottom as part of the failure summary, which is what I
        // was going for.  Maybe something like
        // https://stackoverflow.com/questions/29979636/how-to-add-string-to-phpunit-failure-message-universally
        // would work?
        echo "\n*** LocalArena table ID: " . $this->table_->localarena_table_id . "\n\n";
        parent::onNotSuccessfulTest($exc);
  }

  // Individual test suites can override this to customize table
  // setup.
  protected function defaultTableParams(): TableParams
  {
    $params = new TableParams();
    $params->game = $this::LOCALARENA_GAME_NAME;
    $params->playerCount = 2;
    return $params;
  }

  private function deferredInit(): void
  {
    // echo '** deferredInit() call' ."\n";
    if (is_null($this->table_)) {
      // XXX: Move TableManager et al. into namespaces.
      $this->initTable($this->defaultTableParams());
    }
  }

  protected function initTable(TableParams $tableParams): void
  {
    // echo '** initTable() call' ."\n";
    if (!is_null($this->table_)) {
      throw new \Exception('Table has already been initialized!');
    }

    $tableParams->game = $this::LOCALARENA_GAME_NAME;

    // Unless the test asked for a specific legacy scope, give the
    // table this test's own private scope, so that its legacy data is
    // isolated by default (see `legacyScope()`).
    if ($tableParams->legacy_scope === null) {
      $tableParams->legacy_scope = $this->legacyScope();
    }

    $table_manager = new \TableManager();
    $this->table_ = $table_manager->createTable($tableParams);

    // XXX: This is a problem; a lot of our code assumes that that
    // there is always a current player.  That's not really true
    // in general in these integration tests; it'll also cause
    // problems for supporting spectators, I imagine.
    $this->table_->currentPlayer = $this->playerByIndex(0)->id();
  }

  // Returns an array of `PlayerPeer`.
  public function players()
  {
    $this->deferredInit();

    $players = [];
    $rows = $this->table()->getCollectionFromDB('SELECT * FROM `player` WHERE TRUE');
    foreach ($rows as $player_id => $row) {
      $players[] = new PlayerPeer($this, $row);
    }

    return $players;
  }

  // Returns a `PlayerPeer` for the active player.  Throws an
  // exception unless the table is in an "activeplayer" state.
  //
  // XXX: This does not actually throw an exception yet; it'll
  // happily return whoever the *last* active player was.
  public function activePlayer(): PlayerPeer
  {
    $this->deferredInit();
    return $this->playerById($this->table()->getActivePlayerId());
  }

  // TODO: Add a helper `multiactivePlayers()` that returns all
  // active players when the game is in a multiactive state.

  public function playerByIndex(int $index): PlayerPeer
  {
    $this->deferredInit();
    $rows = array_values($this->table()->getCollectionFromDB('SELECT * FROM `player` WHERE TRUE ORDER BY `player_id` ASC'));
    if (!array_key_exists($index, $rows)) {
        throw new \Exception('Player index is out of range: ' . $index);
    }
    return new PlayerPeer($this, $rows[$index]);
  }

  public function playerById(string $player_id): PlayerPeer
  {
    $this->deferredInit();
    $row = $this->table()->getObjectFromDB('SELECT * FROM `player` WHERE `player_id` = ' . $player_id);
    return new PlayerPeer($this, $row);
  }

  // XXX: Returns Table.
  public function table()
  {
    $this->deferredInit();
    return $this->table_;
  }

  protected function gamedatas()
  {
    $this->deferredInit();

    // XXX: This depends on the current player.
    return $this->table()->getFullDatas();
  }

  // XXX: Deprecate/remove in favor of `state()`?
  protected function gamestate()
  {
    $this->deferredInit();
    return $this->gamedatas()['gameState'];
  }

  public function state(): GameStateInfo
  {
    $state = $this->table()->getStateForNotif(/*includeMultiactive=*/ true);
    return new GameStateInfo($state);
  }

  // Asserts that the game's state machine is in the $expected_state_id state.
  public function assertGameState(int $expected_state_id, ?string $message = null): void
  {
    $expected_state_name = $this->table()->gamestate->machinestates[$expected_state_id]['name'];
    $actual_state_name = $this->table()->gamestate->state()['name'];
    $actual_state_id = $this->table()->getCurrentStateId();

    $this->assertEquals(
      $expected_state_id,
      $actual_state_id,
      'Expected the game to be in state "' .
        $expected_state_name .
        '" (' .
        $expected_state_id .
        ') but it is in state "' .
        $actual_state_name .
        '" (' .
        $actual_state_id .
        ') instead.' .
        ($message === null ? '' : '  ' . $message)
    );
  }

  // Returns a `StatPeer` for the given table statistic.  (For player
  // statistics, see `PlayerPeer::stat()`.)
  public function tableStat(string $name): StatPeer
  {
    return new StatPeer($this, $name, null);
  }

  // Returns the value of the given player statistic for every player
  // at the table, as an array mapping player ID to the stat's value
  // (cast to its declared type), in ascending player-ID order.
  //
  // Warning: if you assert on the returned array directly, remember
  // that PHP array comparisons can be key-order-sensitive (e.g.
  // `assertSame()` fails for identical dicts whose keys are merely
  // ordered differently).  Prefer `assertPlayerStats()`, which
  // canonicalizes both sides before comparing.
  public function playerStatValues(string $name): array
  {
    $values = [];
    foreach ($this->players() as $player) {
      $values[intval($player->id())] = $player->stat($name)->get();
    }
    ksort($values);
    return $values;
  }

  // Asserts that the given table statistic has the expected value.
  public function assertTableStat($expected, string $name, string $message = ''): void
  {
    $this->assertEquals(
      $expected,
      $this->tableStat($name)->get(),
      'Unexpected value for table statistic "' . $name . '".' . ($message === '' ? '' : '  ' . $message)
    );
  }

  // Asserts that the given player statistic has the expected value
  // for every player at the table.  `$expected` maps player ID to the
  // expected value; it may be in any order, but must cover every
  // player (a player missing from `$expected` is a failure, not a
  // "don't care").
  public function assertPlayerStats(array $expected, string $name, string $message = ''): void
  {
    // Canonicalize so that the comparison is order-insensitive and a
    // mismatch produces a readable side-by-side diff.
    $normalized = [];
    foreach ($expected as $player_id => $value) {
      $normalized[intval($player_id)] = $value;
    }
    ksort($normalized);
    $this->assertEquals(
      $normalized,
      $this->playerStatValues($name),
      'Unexpected values for player statistic "' . $name . '".' . ($message === '' ? '' : '  ' . $message)
    );
  }

  // Asserts that the given player statistic has the expected value
  // for `$player`.  (Given a player ID, use `playerById()`.)
  public function assertPlayerStat($expected, string $name, PlayerPeer $player, string $message = ''): void
  {
    $this->assertEquals(
      $expected,
      $player->stat($name)->get(),
      'Unexpected value for player statistic "' .
        $name .
        '" of player ' .
        $player->id() .
        '.' .
        ($message === '' ? '' : '  ' . $message)
    );
  }

  // ==================== Counters ====================
  //
  // Counters are created by the game (in its Game class's
  // constructor), so a test reaches them by name rather than by
  // holding a reference: `counter('credits')` finds the same object
  // the game holds.  The two counters every game has by default are
  // named "playerScore" and "playerScoreAux".
  //
  // A test that needs a counter the game does not define can create
  // one itself -- `$this->table()->counterFactory->createPlayerCounter(...)`
  // followed by `initDb()` -- at any point after the table exists.

  // Returns the counter with the given name.
  public function counter(string $name): Counter
  {
    return $this->table()->counterFactory->localarenaGetCounter($name);
  }

  // Returns the player counter with the given name.
  public function playerCounter(string $name): PlayerCounter
  {
    $counter = $this->counter($name);
    if (!($counter instanceof PlayerCounter)) {
      throw new \Exception('Counter "' . $name . '" is not a player counter.');
    }
    return $counter;
  }

  // Returns the table counter with the given name.
  public function tableCounter(string $name): TableCounter
  {
    $counter = $this->counter($name);
    if (!($counter instanceof TableCounter)) {
      throw new \Exception('Counter "' . $name . '" is not a table counter.');
    }
    return $counter;
  }

  // Returns the value of the given player counter for every player it
  // has a value for, as an array mapping the counter's own key
  // (player id, or player no for a "useNo" counter) to value, in
  // ascending key order.
  //
  // Warning: if you assert on the returned array directly, remember
  // that PHP array comparisons can be key-order-sensitive.  Prefer
  // `assertPlayerCounters()`, which canonicalizes both sides.
  public function playerCounterValues(string $name): array
  {
    $values = $this->playerCounter($name)->getAll();
    ksort($values);
    return $values;
  }

  // Asserts that the given table counter has the expected value.
  public function assertTableCounter(int $expected, string $name, string $message = ''): void
  {
    $this->assertSame(
      $expected,
      $this->tableCounter($name)->get(),
      'Unexpected value for table counter "' . $name . '".' . ($message === '' ? '' : '  ' . $message)
    );
  }

  // Asserts that the given player counter has the expected value for
  // `$player`.
  public function assertPlayerCounter(int $expected, string $name, PlayerPeer $player, string $message = ''): void
  {
    $this->assertSame(
      $expected,
      $player->counterValue($name),
      'Unexpected value for player counter "' .
        $name .
        '" of player ' .
        $player->id() .
        '.' .
        ($message === '' ? '' : '  ' . $message)
    );
  }

  // Asserts that the given player counter has the expected value for
  // every player it has a value for.  `$expected` maps the counter's
  // own key (player id, or player no for a "useNo" counter) to the
  // expected value; it may be in any order, but must cover every key
  // the counter has (a key missing from `$expected` is a failure, not
  // a "don't care").
  public function assertPlayerCounters(array $expected, string $name, string $message = ''): void
  {
    $normalized = [];
    foreach ($expected as $key => $value) {
      $normalized[intval($key)] = $value;
    }
    ksort($normalized);
    $this->assertSame(
      $normalized,
      $this->playerCounterValues($name),
      'Unexpected values for player counter "' . $name . '".' . ($message === '' ? '' : '  ' . $message)
    );
  }

  // ==================== Notifications ====================

  // Returns the notifications the table has sent, oldest first, each
  // as an array with the keys:
  //
  //   'id'        the gamelog id (ascending; useful for slicing)
  //   'moveId'    the move the notification belongs to
  //   'type'      the notification type ('setPlayerCounter', ...)
  //   'log'       the game-log message ('' for a silent notification)
  //   'args'      the notification's arguments
  //   'recipient' the player it was sent to, or null for all players
  //
  // With `$type`, only notifications of that type are returned; with
  // `$recipient_player_id`, only those that the given player received
  // (which includes the ones sent to everybody).
  public function notifs(?string $type = null, ?int $recipient_player_id = null): array
  {
    $rows = $this->table()->getObjectListFromDB('SELECT * FROM `gamelog` ORDER BY `gamelog_id` ASC');

    $notifs = [];
    foreach ($rows as $row) {
      $notif = json_decode($row['gamelog_notification'], /*associative=*/ true);
      $recipient = $row['gamelog_player'] === null ? null : intval($row['gamelog_player']);

      if ($type !== null && ($notif['notification_type'] ?? null) !== $type) {
        continue;
      }
      if ($recipient_player_id !== null && $recipient !== null && $recipient !== $recipient_player_id) {
        continue;
      }

      $notifs[] = [
        'id' => intval($row['gamelog_id']),
        'moveId' => intval($row['gamelog_move_id']),
        'type' => $notif['notification_type'] ?? null,
        'log' => $notif['notification_log'] ?? null,
        'args' => $notif['args'] ?? null,
        'recipient' => $recipient,
      ];
    }
    return $notifs;
  }

  // ==================== Legacy-games data ====================
  //
  // Legacy data (the `$this->bga->legacy` API) persists ACROSS tables,
  // in the shared `localarena` database -- so, unlike everything else
  // about a table, it can (and frequently must) be arranged BEFORE the
  // table for a test case is set up: games read legacy data during
  // `setupNewGame()`.
  //
  // The seeding helpers below therefore write straight to the legacy
  // store, without touching (or creating) the test's table.  Because a
  // test's table is created lazily, a test can seed legacy data first
  // and then let table creation (e.g. the first `table()` call) run
  // setup code that reads it.  Use `presetPlayerId()` for the player
  // IDs that `stGameSetup()` will later assign.
  //
  // Tests are ISOLATED BY DEFAULT: each test case gets its own legacy
  // scope (see `legacyScope()`), which `initTable()` assigns to the
  // test's table and which the helpers below seed into and read from.
  // No test can see another test's legacy data -- nor data from
  // previous runs, nor real ("game name"-scoped) data from interactive
  // play against the same database -- and nothing needs to be cleared.

  // The legacy scope for this test case, allocated on first use.
  private ?string $legacy_scope_ = null;

  // Returns this test case's own legacy scope: the scope that
  // `initTable()` gives the test's table by default, and that
  // `seedLegacyData()`/`seedLegacyTeamData()` write into.  Unique per
  // test case (and per run) -- the id is allocated by the database, so
  // uniqueness is guaranteed rather than probabilistic -- which is
  // what makes tests' legacy data isolated by default.
  //
  // A test that creates FURTHER tables itself (e.g. to exercise legacy
  // data flowing from one table to the next) should set
  // `TableParams::$legacy_scope` to this value for each of them.
  public function legacyScope(): string
  {
    if ($this->legacy_scope_ === null) {
      $this->legacy_scope_ = 'test/' . \LocalArenaLegacyStore::allocateScopeId();
    }
    return $this->legacy_scope_;
  }

  // Returns the player ID that `stGameSetup()` assigns to the player
  // with the given zero-based index.  Valid before the table exists.
  public static function presetPlayerId(int $index): int
  {
    return \Table::LOCALARENA_FIRST_PLAYER_ID + $index;
  }

  // Returns the IDs of all players that `stGameSetup()` will seat (in
  // seating order).  Valid before the table exists.
  public static function presetPlayerIds(): array
  {
    return array_map(fn($i) => self::presetPlayerId($i), range(0, LOCALARENA_PLAYER_COUNT - 1));
  }

  private function legacyStore(): \LocalArenaLegacyStore
  {
    return new \LocalArenaLegacyStore($this::LOCALARENA_GAME_NAME, $this->legacyScope());
  }

  // Seeds legacy data for the given player (0 for game-global data)
  // into this test's legacy scope, as if a previous table had stored
  // it.  May be called before the table is set up.  A zero or negative
  // $ttl seeds already-expired data (useful for exercising TTL
  // behavior).
  public function seedLegacyData(int $player_id, string $key, $value, int $ttl = 365): void
  {
    \LocalArenaLegacyStore::validateKey($key);
    $this->legacyStore()->setPlayerData($player_id, $key, json_encode($value), $ttl);
  }

  // Seeds legacy TEAM data for the given set of players (default: all
  // of the players that `stGameSetup()` will seat), as if a previous
  // table with exactly those players had stored it.  May be called
  // before the table is set up.
  public function seedLegacyTeamData($value, ?array $player_ids = null, int $ttl = 365): void
  {
    $signature = localarenaLegacyTeamSignature($player_ids ?? self::presetPlayerIds());
    $this->legacyStore()->setTeamData($signature, json_encode($value), $ttl);
  }

  // Reads back the legacy value stored for ($player_id, $key), or
  // $default if there is none; for assertions.  Does not require (or
  // create) the table.
  public function legacyValue(string $key, int $player_id, $default = null)
  {
    $rows = $this->legacyStore()->getPlayerData($player_id, $key);
    if (!array_key_exists($key, $rows)) {
      return $default;
    }
    return json_decode($rows[$key], /*associative=*/ true);
  }

  // Reads back the legacy team value stored for the given set of
  // players (default: the players that `stGameSetup()` seats), or
  // $default if there is none; for assertions.
  public function legacyTeamValue(?array $player_ids = null, $default = null)
  {
    $signature = localarenaLegacyTeamSignature($player_ids ?? self::presetPlayerIds());
    $json = $this->legacyStore()->getTeamData($signature);
    if ($json === null) {
      return $default;
    }
    return json_decode($json, /*associative=*/ true);
  }

  // XXX: How will we get notifs routed back to the test fixtures as
  // they are sent?  `notifs()` above reads them back out of the
  // gamelog after the fact, which is enough to assert on what was
  // sent, but not to observe the sending itself.

  // TODO: Clean up the table after successful tests.
}

// class TablePeer {
//     private Table $table_;
// }

class PlayerPeer
{
  private IntegrationTestCase $itc_;

  // XXX: Should this be PlayerIdString?
  private string $id_;
    private int $no_;
  private string $name_;

  private function table()
  {
    return $this->itc_->table();
  }

  public function __construct($itc, $row)
  {
    $this->itc_ = $itc;
    $this->id_ = $row['player_id'];
    $this->no_ = intval($row['player_no']);
    $this->name_ = $row['player_name'];
  }

  // XXX: Should this be PlayerIdString?
  public function id(): string
  {
    return $this->id_;
  }

    public function no(): int
    {
        return $this->no_;
    }

  public function name(): string
  {
    return $this->name_;
  }

  // XXX: This is duplicated with `CharacterPeer::act()`; do we need
  // to consolidate them?
  public function act(string $action_name, $action_args = []): void
  {
    echo 'Player ' . $this->id() . ' performing action "' . $action_name . '"...' . "\n";

    // For AT_json args.
    foreach ($action_args as $k => $v) {
      if (is_array($action_args[$k])) {
        $action_args[$k] = json_encode($action_args[$k]);
      }
      if (is_bool($action_args[$k])) {
        $action_args[$k] = $action_args[$k] ? 'true' : 'false';
      }
    }

    $this->table()->doAction(
      $this->table()->gameServer,
      array_merge($action_args, [
        'bgg_actionName' => $action_name,
        'bgg_player_id' => $this->id(),
      ])
    );
  }

  public function gamedatas()
  {
    // XXX: This needs to call getFullDatas() with *this player*
    // as the current player.
    return $this->table()->getFullDatas();
  }

  public function state(): GameStateInfo
  {
    $state = $this->table()->getStateForClient($this->id(), /*includeMultiactive=*/ true);
    return new GameStateInfo($state);
  }

  // Returns a `StatPeer` for the given player statistic of this
  // player.
  public function stat(string $name): StatPeer
  {
    return new StatPeer($this->itc_, $name, $this->id_);
  }

  // Returns the value of the given player counter for this player,
  // addressing the counter by whichever of player id and player no it
  // is keyed by (see `setUseNo()`).
  public function counterValue(string $name): int
  {
    $counter = $this->itc_->playerCounter($name);
    return $counter->get($counter->getUseNo() ? $this->no() : intval($this->id()));
  }
  // TODO: Add accessors for things like "is this player active?"
}

// A peer for a single statistic: either a table statistic, or one
// player's value of a player statistic.
class StatPeer
{
  private IntegrationTestCase $itc_;
  private string $name_;

  // The owning player's ID, or null for a table statistic.
  //
  // XXX: Should this be PlayerIdString?
  private ?string $player_id_;

  public function __construct(IntegrationTestCase $itc, string $name, ?string $player_id)
  {
    $this->itc_ = $itc;
    $this->name_ = $name;
    $this->player_id_ = $player_id;
  }

  public function name(): string
  {
    return $this->name_;
  }

  // Returns the statistic's current value, cast to its declared type
  // ("int", "float", or "bool").
  public function get()
  {
    if ($this->player_id_ === null) {
      return $this->itc_->table()->tableStats->get($this->name_);
    }
    return $this->itc_->table()->playerStats->get($this->name_, intval($this->player_id_));
  }

  // Sets the statistic's value.  Prefer producing stats by driving
  // the game through actions; reach for this only when a test needs
  // to arrange stat state directly (e.g. to exercise game code that
  // derives values from stats).
  public function set($value): void
  {
    if ($this->player_id_ === null) {
      $this->itc_->table()->tableStats->set($this->name_, $value);
    } else {
      $this->itc_->table()->playerStats->set($this->name_, $value, intval($this->player_id_));
    }
  }
}

// TODO: Should we use this in the implementation of LocalArena as well?
class GameStateInfo
{
  private $state_;

  // $state is an associative array, such as that returned by
  // `table()->getStateForClient()` (that is, with any args function
  // called, and with private data only for one player) or
  // `table->getStateForNotif()` (with any args function called but
  // private data for all players).
  public function __construct($state)
  {
    $this->state_ = $state;
  }

  public function name(): string
  {
    return $this->state_['name'];
  }

  public function type(): string
  {
    return $this->state_['type'];
  }

  public function args()
  {
    return $this->state_['args'];
  }
}
