<?php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\ConnectionInterface;

use Ratchet\MessageComponentInterface;

define("APP_BASE_PATH", "/src/");
define("APP_GAMEMODULE_PATH", "/src/");

set_include_path(get_include_path() . PATH_SEPARATOR . APP_GAMEMODULE_PATH);

require_once "/src/localarena_config.inc.php";
require "/src/vendor/autoload.php";

require LOCALARENA_GAME_NAME . "/" . LOCALARENA_GAME_NAME . ".game.php";

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
        $currentPlayerId = str_replace(
            "/",
            "",
            $from->httpRequest->getUri()->getPath()
        );
        $game = new (''.LOCALARENA_GAME_NAME)();
        $game->doAction($this, json_decode($msg, true));
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
