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
    protected $table;

    protected function setUp(): void {
        // XXX: Move TableManager et al. into namespaces.
        $table_manager = new \TableManager();

        $params = new \TableParams();
        $params->game = $this::LOCALARENA_GAME_NAME;
        $this->table = $table_manager->createTable($params);
    }

    // TODO: Clean up the table after successful tests.
}
