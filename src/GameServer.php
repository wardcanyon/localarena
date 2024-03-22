<?php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\ConnectionInterface;

use Ratchet\MessageComponentInterface;

require dirname(__DIR__) . '/game/vendor/autoload.php';

define("APP_BASE_PATH",'D:/wamp64\www\game/');
define("APP_GAMEMODULE_PATH",'D:/wamp64\www\game/');   

include('thecrew/thecrew.game.php');

class GameServer implements MessageComponentInterface {
    
    public function __construct() {
        $this->clients = array();
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $currentPlayerId = str_replace("/","", $conn->httpRequest->getUri()->getPath());
        $this->clients[intval($currentPlayerId)] = $conn;
        echo "New connection! ({$currentPlayerId})\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        
        $currentPlayerId = str_replace("/","", $from->httpRequest->getUri()->getPath());
        $game = new thecrew();
        $game->doAction( $this, json_decode($msg, true));
    }
    
    public function onClose(ConnectionInterface $conn) {
        $currentPlayerId = str_replace("/","", $conn->httpRequest->getUri()->getPath());
        unset($this->clients[$currentPlayerId]);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        
        $conn->close();
    }
    
    public function notifPlayer($player_id, $data)
    {
        if(isset($this->clients[intval($player_id)]))
        {
            $this->clients[intval($player_id)]->send($data);
        }
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new GameServer()
            )
        ),
    3000
    );

$server->run();