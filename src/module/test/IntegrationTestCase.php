<?php declare(strict_types=1);
namespace LocalArena\Test;

define('DEV_MODE', 1);

// These need to be set so that games can include
// APP_GAMEMODULE_PATH.'module/table/table.game.php' and
// APP_BASE_PATH.''view/common/game.view.php'.
define('APP_BASE_PATH', '/src/localarena/');
define('APP_GAMEMODULE_PATH', '/src/localarena/');

// Each game should be in a subdirectory of this one.
define('LOCALARENA_GAME_PATH', '/src/game/');

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
    // $this->table_ = null;
    // $this->gamedatas_ = null;
  }

  // Individual test suites can override this to customize table
  // setup.
  protected function defaultTableParams(): \TableParams
  {
    $params = new \TableParams();
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

  protected function initTable(\TableParams $tableParams): void
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
  protected function players()
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
  protected function activePlayer(): PlayerPeer
  {
    $this->deferredInit();
    return $this->playerById($this->table()->getActivePlayerId());
  }

  // TODO: Add a helper `multiactivePlayers()` that returns all
  // active players when the game is in a multiactive state.

  protected function playerByIndex(int $index): PlayerPeer
  {
    $this->deferredInit();
    $row = $this->table()->getObjectFromDB('SELECT * FROM `player` WHERE TRUE ORDER BY `player_id` ASC LIMIT 1');
    return new PlayerPeer($this, $row);
  }

  protected function playerById(string $player_id): PlayerPeer
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
  public function assertGameState(int $expected_state_id): void
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
        ') instead.'
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

  private function table()
  {
    return $this->itc_->table();
  }

  public function __construct($itc, $row)
  {
    $this->itc_ = $itc;
    $this->id_ = $row['player_id'];
  }

  // XXX: Should this be PlayerIdString?
  public function id(): string
  {
    return $this->id_;
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
  // TODO: Add accessors for things like "is this player active?"
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
