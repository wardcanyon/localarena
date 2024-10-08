<?php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\ConnectionInterface;

use Ratchet\MessageComponentInterface;

define("APP_BASE_PATH", "/src/");
define("APP_GAMEMODULE_PATH", "/src/");
define("LOCALARENA_GAME_PATH", "/src/");

set_include_path(get_include_path() . PATH_SEPARATOR . APP_GAMEMODULE_PATH);

require_once APP_GAMEMODULE_PATH . "/localarena_config.inc.php";
require_once APP_GAMEMODULE_PATH . "/vendor/autoload.php";

require_once APP_GAMEMODULE_PATH . "/module/tablemanager/tablemanager.php";

class GameServer implements MessageComponentInterface
{
    public function __construct()
    {
        $this->clients = [];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $currentPlayerId = str_replace(
            "/",
            "",
            $conn->httpRequest->getUri()->getPath()
        );
        $this->clients[intval($currentPlayerId)] = $conn;
        echo "New connection! ({$currentPlayerId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        // XXX: We need more plumbing to support multiple table IDs.
        $table_id = 1;

        $currentPlayerId = str_replace(
            "/",
            "",
            $from->httpRequest->getUri()->getPath()
        );
        $table_manager = new TableManager();
        $game = $table_manager->getTable($table_id);

        try {
            $game->doAction($this, json_decode($msg, true));
        } catch (\Exception $e) {
            // TODO: Ship the error to the client for display!
            echo "An error has occurred while handling an action: " . $e->getMessage();
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $currentPlayerId = str_replace(
            "/",
            "",
            $conn->httpRequest->getUri()->getPath()
        );
        unset($this->clients[$currentPlayerId]);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }

    public function notifPlayer($player_id, $data)
    {
        if (isset($this->clients[intval($player_id)])) {
            $this->clients[intval($player_id)]->send($data);
        }
    }
}

$server = IoServer::factory(
    new HttpServer(new WsServer(new GameServer())),
    3000
);

$server->run();
