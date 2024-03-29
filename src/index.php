<?php

define("DEV_MODE", 1);

define("APP_BASE_PATH", "/src/");
define("APP_GAMEMODULE_PATH", "/src/");

require_once APP_BASE_PATH . "/localarena_config.inc.php";
require_once APP_BASE_PATH . "/module/tablemanager/tablemanager.php";

// XXX: Un-hardwire.  In particular, at the moment we'll create a new table if this table_id does not exist; but it'll have the sequentially-next table ID, not this one.
$table_id = 1;

// XXX: Un-hardwire.
$currentPlayer = 2317679;

if (DEV_MODE) {
    if (isset($_GET["testplayer"])) {
        $currentPlayer = $_GET["testplayer"];
    }

    // XXX: Either support or remove this.  If we want to keep it,
    // we'll need to move it lower, once we have $game.
    //
    // if (isset($_GET["loadDatabase"])) {
    //    $view->game->loadDatabase();
    // }
}

// The game-specific view code expects this.
//
// XXX: Find this a better home.
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

$table_manager = new TableManager();

$game = $table_manager->getTable($table_id);
if (is_null($game)) {
    $params = new TableParams();
    $params->game = LOCALARENA_GAME_NAME;
    $game = $table_manager->createTable($params);
}
// XXX: If the table is an existing one, check that the current
// player is part of the game.
$game->currentPlayer = $currentPlayer;

$game_name = $game->localarenaGetGameName();

require $game_name . "/" . $game_name . ".view.php";
$view_class_name = "view_{$game_name}_{$game_name}";
$view = new $view_class_name($game);

// XXX: Either support this or remove it.
if (isset($_GET["replayFrom"])) {
    $view->game->replayFrom = $_GET["replayFrom"];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tutorial: Hello Dojo!</title>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous"/>
    <link rel="stylesheet" href="./dijit/themes/claro/claro.css"/>
    <link href="./fa/css/all.css" rel="stylesheet">
    <link rel="stylesheet" href="./site.css"/>
    <link rel="stylesheet" href="./<?= "$game_name" ?>/<?= "$game_name" ?>.css"/>

</head>
<body class="claro">
    <?php $view->display(); ?>

    <!-- load Dojo -->
    <script>
     // N.B.: This is a bit of a hack so that when our modules try to
     // assign the classes that they define to a property of these
     // objects, the objects are defined.
     //
     // The fact that we need to do this probably means that we aren't
     // doing something quite right with how we `declare()` classes for
     // Dojo.
     var ebg = {};
     ebg.core = {};

    var dojoConfig = {
            async: true,
            baseUrl: './dojo',
            packages: [
            {
                name: "jquery",
                location: "/game/",
                main: "jquery-3.5.1.min"
            },
            {
                name: "ebg",
                location: "/game/ebg/"
            },
            {
                name: "socketio",
                // XXX: We probably want to avoid
                // hardwiring "localhost" here.
                location: "http://localhost:3000/socket.io/",
                main: "socket.io"
            },
            {
                name: "bgagame",
                location: "/game/<?= "$game_name" ?>/"
            }]
        };

    function escapeRegExp(str) {
    	  return str.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); // $& means the whole matched string
    	};

	function replaceAll(str, find, replace) {
		  return str.replace(new RegExp(escapeRegExp(find), 'g'), replace);
		};

    function _(val)
	{
		return val;
	};

    function __(val)
	{
		return val;
	};

	function $(val)
	{
		return dojo.byId(val);
	};

     g_gamethemeurl = "/game/<?= "$game_name" ?>/";

    </script>
    <script src="dojo/dojo.js"></script>

    <script>
     require(["dojo", "dojo/_base/unload","bgagame/<?= "$game_name" ?>", "dojo/domReady!"], function( dojo, baseUnload ) {
         // XXX: These are not fully supported yet.
         window.g_replayFrom = -1;
         window.g_archive_mode = false;

         // Sound is unsupported.  (These stubs were necessary for the "hearts" example.)
         window.playSound = function() {};

         gameui = new bgagame.<?= "$game_name" ?>();
        gameui.player_id = <?= $view->game->currentPlayer ?>;
        gameui.current_player_name="Mistergos1";
	 gameui.completesetup( "<?= "$game_name" ?>", <?= $view->getFullDatasAsJson() ?>);
		gameui.logAll(<?= json_encode($view->game->getLogs()) ?>);

		<?php if ($view->game->replayFrom > 0) {
      echo "gameui.replay = true;";
      $logs = $view->game->getReplay();
      foreach ($logs as $log) {
          echo "gameui.notifqueue.addEvent(JSON.parse('" .
              $log["gamelog_notification"] .
              "'));\n";
      }
      echo "gameui.notifqueue.processNotif();";
  } ?>

        });
    </script>
</body>
</html>
