<?php declare(strict_types=1);
namespace LocalArena\Test;

define("DEV_MODE", 1);

// These need to be set so that games can include
// APP_GAMEMODULE_PATH.'module/table/table.game.php' and
// APP_BASE_PATH.''view/common/game.view.php'.
define("APP_BASE_PATH", "/src/localarena/");
define("APP_GAMEMODULE_PATH", "/src/localarena/");

// Each game should be in a subdirectory of this one.
define("LOCALARENA_GAME_PATH", "/src/game/");

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


require_once APP_GAMEMODULE_PATH . "module/table/table.game.php";
require_once APP_GAMEMODULE_PATH . "module/tablemanager/tablemanager.php";


class IntegrationTestCase extends \PHPUnit\Framework\TestCase {
    private $table_ = null;

    protected function setUp(): void {
        // $this->table_ = null;
        // $this->gamedatas_ = null;
    }

    // Individual test suites can override this to customize table
    // setup.
    protected function defaultTableParams(): \TableParams {
        $params = new \TableParams();
        $params->game = $this::LOCALARENA_GAME_NAME;
        $params->playerCount = 2;
        return $params;
    }

    private function deferredInit(): void {
        // echo '** deferredInit() call' ."\n";
        if (is_null($this->table_)) {
            // XXX: Move TableManager et al. into namespaces.
            $this->initTable($this->defaultTableParams());
        }
    }

    protected function initTable(\TableParams $tableParams): void {
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
    protected function players() {
        $this->deferredInit();

        $players = [];
        $rows = $this->table()->getCollectionFromDB('SELECT * FROM `player` WHERE TRUE');
        foreach ($rows as $player_id => $row) {
            $players[] = new PlayerPeer($this, $row);
        }

        return $players;
    }

    protected function playerByIndex(int $index): PlayerPeer {
        $row = $this->table()->getObjectFromDB('SELECT * FROM `player` WHERE TRUE ORDER BY `player_id` ASC LIMIT 1');
        return new PlayerPeer($this, $row);
    }

    // XXX: Returns Table.
    public function table() {
        $this->deferredInit();
        return $this->table_;
    }

    protected function gamedatas() {
        $this->deferredInit();

        // XXX: This depends on the current player.
        return $this->table()->getFullDatas();
    }

    protected function gamestate() {
        $this->deferredInit();
        return $this->gamedatas()['gameState'];
    }

    // XXX: How will we get notifs routed back to the test fix fixtures?

    // TODO: Clean up the table after successful tests.
}

// class TablePeer {
//     private Table $table_;
// }

class PlayerPeer {
    private IntegrationTestCase $itc_;

    // XXX: Should this be PlayerIdString?
    private string $id_;

    private function table() {
        return $this->itc_->table();
    }

    public function __construct($itc, $row) {
        $this->itc_ = $itc;
        $this->id_ = $row['player_id'];
    }

    // XXX: Should this be PlayerIdString?
    public function id(): string {
        return $this->id_;
    }

    public function act(string $action_name, $action_args = []): void {
        echo 'Player ' . $this->id() . ' performing action "' . $action_name . '"...' . "\n";

        $this->table()->doAction(
            $this->table()->gameServer,
            array_merge($action_args,
                        [
                            'bgg_actionName' => $action_name,
                            'bgg_player_id' => $this->id(),
                        ]));
    }
}
