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

  // XXX: How will we get notifs routed back to the test fix fixtures?

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

  private function table()
  {
    return $this->itc_->table();
  }

  public function __construct($itc, $row)
  {
    $this->itc_ = $itc;
    $this->id_ = $row['player_id'];
    $this->no_ = intval($row['player_no']);
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
