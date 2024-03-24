 <?php
 require_once APP_GAMEMODULE_PATH . "module/table/feException.php";
 require_once APP_GAMEMODULE_PATH . "module/table/GameState.php";
 require_once APP_GAMEMODULE_PATH . "module/table/APP_GameAction.php";
 require_once APP_GAMEMODULE_PATH . "module/table/deck.php";
 require_once APP_GAMEMODULE_PATH . "view/common/util.php";

class APP_Object
{
    function __construct() {
    }

    function dump($v, $value)
    {
        echo "$v=";
        var_dump($value);
    }

    function info($value)
    {
        echo "$value\n";
    }

    function trace($value)
    {
        echo "$value\n";
    }

    function debug($value)
    {
        echo "$value\n";
    }

    function watch($value)
    {
        echo "$value\n";
    }

    function warn($value)
    {
        echo "$value\n";
    }

    function error($msg)
    {
        echo "$msg\n";
    }
}

class APP_DbObject extends APP_Object
{
    static $connstat = null;

    public $conn;

    // XXX: Hmm, yikes.  The way that this is being done now, multiple
    // instantiations will overwrite each other's $connstat.
    //
    // We need a way to make the database interface functions
    // available to e.g. burglebrostwo's CardManager without that
    // issue.
    function __construct() {
        parent::__construct();

        # These are provided by Docker Compose; see "compose.yaml".
        $this->servername = getenv("DB_HOST");
        $this->username = getenv("DB_USER");
        $this->dbname = getenv("DB_NAME");
        $this->password = trim(
            file_get_contents(getenv("DB_PASSWORD_FILE_PATH"))
        );

        $this->currentPlayer = 0;
        $this->replayFrom = 0;

        // Create connection
        $this->conn = new mysqli(
            $this->servername,
            $this->username,
            $this->password,
            $this->dbname
        );
        self::$connstat = $this->conn;

        // Check connection
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        /* Activation du reporting */
        $driver = new mysqli_driver();
        $driver->report_mode = MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_INDEX;
    }

     protected function getCollectionFromDB(
         $sql,
         $bSingleValue = false,
         $low_priority_select = false
     ) {
         $ret = [];
         try {
             if (!($data = $this->conn->query($sql))) {
                 var_dump($this->conn->error);
             }
             $fetch = mysqli_fetch_all($data, MYSQLI_ASSOC);

             foreach ($fetch as $row) {
                 $key = array_key_first($row);
                 $ret[$row[$key]] = $row;
             }

             if ($bSingleValue && count($ret)) {
                 throw feException("too many results");
             }
         } catch (mysqli_sql_exception $e) {
             var_dump($sql);
             throw $e;
         }
         return $ret;
     }

     function getNonEmptyCollectionFromDB($sql)
     {
         $ret = [];
         try {
             if (!($data = $this->conn->query($sql))) {
                 var_dump($this->conn->error);
             }
             $fetch = mysqli_fetch_all($data, MYSQLI_ASSOC);

             foreach ($fetch as $row) {
                 $key = array_key_first($row);
                 $ret[$row[$key]] = $row;
             }

             if (count($ret) == 0) {
                 throw feException("empty results");
             }
         } catch (mysqli_sql_exception $e) {
             var_dump($sql);
             throw $e;
         }
         return $ret;
     }

     function getObjectFromDB($sql, $low_priority_select = false)
     {
         $ret = [];
         try {
             if (!($data = $this->conn->query($sql))) {
                 var_dump($this->conn->error);
             }
             $ret = mysqli_fetch_assoc($data);
         } catch (Exception $e) {
             var_dump($sql);
             throw $e;
         }
         return $ret;
     }

     function getNonEmptyObjectFromDB($sql, $low_priority_select = false)
     {
         $ret = [];
         try {
             if (!($data = $this->conn->query($sql))) {
                 var_dump($this->conn->error);
             }

             if ($data->num_rows == 0) {
                 throw feException("empty results");
             }

             $ret = mysqli_fetch_assoc($data);
         } catch (Exception $e) {
             var_dump($sql);
             throw $e;
         }
         return $ret;
     }

     static function getUniqueValueFromDB($sql, $low_priority_select = false)
     {
         $ret = "";
         try {
             if (!($data = self::$connstat->query($sql))) {
                 var_dump(self::$connstat->error);
             }
             if ($data->num_rows > 1) {
                 throw new feException("too many results");
             }

             $row = mysqli_fetch_row($data);

             $ret = $row[0] ?? 0;
         } catch (Exception $e) {
             var_dump($sql);
             throw $e;
         }
         return $ret;
     }

     function getObjectListFromDB($sql, $bUniqueValue = false)
     {
         $ret = [];
         try {
             if (!($data = $this->conn->query($sql))) {
                 var_dump($this->conn->error);
             }
             if ($bUniqueValue) {
                 $fetch = mysqli_fetch_all($data, MYSQLI_ASSOC);
                 foreach ($fetch as $row) {
                     $key = array_key_first($row);
                     $ret[] = $row[$key];
                 }
             } else {
                 $ret = mysqli_fetch_all($data, MYSQLI_ASSOC);
             }
         } catch (mysqli_sql_exception $e) {
             var_dump($sql);
             throw $e;
         }
         return $ret;
     }

     static function DbQuery($sql, $specific_db = null, $bMulti = false)
     {
         //  var_dump($sql);
         try {
             self::$connstat->query(
                 $sql,
                 $bMulti ? MYSQLI_USE_RESULT : MYSQLI_STORE_RESULT
             );
         } catch (Exception $e) {
             var_dump($sql);
             throw $e;
         }
     }

    function escapeStringForDB($string)
    {
        return $this->conn->real_escape_string($string);
    }

    function DbGetLastId() {
        return $this->conn->insert_id;
    }

    // XXX: This isn't part of the interface of this class; it's
    // something added in LBGA.
     private function saveDatabase()
     {
         $dir = "/src/databaseExport/database.sql";
         exec(
             "mysqldump --user={$this->username} --password={$this->password} --host={$this->servername} {$this->dbname} --result-file={$dir} 2>&1",
             $output
         );
     }

    // XXX: This isn't part of the interface of this class; it's
    // something added in LBGA.
    function loadDatabase()
     {
         $dir = "/src/databaseExport/database.sql";
         if (file_exists($dir)) {
             exec(
                 "mysql --user={$this->username} --password={$this->password} --host={$this->servername} {$this->dbname} < {$dir} 2>&1",
                 $output
             );
         }
     }
}

class APP_GameClass extends APP_DbObject
{
}

class Table extends APP_GameClass
 {
     // This contains the data defined in `stats.inc.php`.
     //
     // XXX: Is this available in the actual BGA implementation? Do we
     // need to hide it?
     //
     // XXX: Why do stats work for "thecrew" (or at least not throw
     // errors) but don't for "emppty" and "burglebrostwo""?
     public $stats_type;

     public $game_options;

     function __construct()
     {
         parent::__construct();

         include $this->getGameName() . "/stats.inc.php";
         $this->stats_type = $stats_type;

         include $this->getGameName() . "/gameoptions.inc.php";
         $this->game_options = $game_options;

         include $this->getGameName() . "/material.inc.php";
         include $this->getGameName() . "/states.inc.php";
         include_once $this->getGameName() .
             "/" .
             $this->getGameName() .
             ".action.php";

         $this->gameStateLabels = [
             "currentState" => 1,
             "activePlayerId" => 2,
             "moveId" => 3,
         ];
         $this->gamestate = new GameState($this, $machinestates);
     }

     function lbgaSetDefaultOptions() {
         foreach ($this->game_options as $option_id => $option_desc) {
             $this->lbgaSetGameStateInitialValue($option_id, $option_desc['default']);
         }
     }

     function getNew($path)
     {
         $vpath = explode(".", $path);
         $classname = end($vpath);
         $obj = new $classname();
         $obj->game = $this;
         return $obj;
     }

     protected function getGameName()
     {
         return "noname";
     }

     protected function setupNewGame($players, $options = [])
     {
     }

     protected function getAllDatas()
     {
     }

     function getReplay()
     {
         $sql =
             "select * from gamelog where gamelog_move_id >= " .
             $this->replayFrom .
             " and (gamelog_player IS NULL or gamelog_player = " .
             $this->getCurrentPlayerId() .
             ") order by gamelog_id";
         $logs = $this->getObjectListFromDB($sql);
         return $logs;
     }

     function getLogs()
     {
         $sql =
             "select * from gamelog where (gamelog_player IS NULL or gamelog_player = " .
             $this->getCurrentPlayerId() .
             ")";
         if ($this->replayFrom > 0) {
             $sql .= " and gamelog_move_id < " . $this->replayFrom;
         }
         $sql .= "  order by gamelog_id";
         $logs = $this->getObjectListFromDB($sql);
         return $logs;
     }

     function getMediumDatas()
     {
         $ret = [];
         $ret["id"] = $this->getCurrentStateId();
         $ret["active_player_id"] = $this->getActivePlayerId();
         $ret["multiactive"] = $this->gamestate->getActivePlayerList();
         $ret["players"] = $this->loadPlayersUIInfos();
         $state = $this->gamestate->state();
         if (isset($state["args"])) {
             $mname = $state["args"];
             $ret["args"] = $this->$mname();
         }
         return $ret;
     }

     function getFullDatas()
     {
         $ret = $this->getMediumDatas();

         if ($this->replayFrom > 0) {
             $data = $this->getUniqueValueFromDB(
                 "select replay_gamedatas from replay where replay_move_id = " .
                     $this->replayFrom .
                     " and replay_player_id=" .
                     $this->getCurrentPlayerId()
             );
             $ret["alldatas"] = json_decode($data);
         } else {
             // XXX:
             $ret["alldatas"] = array_merge(
                 $this->getMediumDatas(),
                 $this->getAllDatas(),
             );
         }

         $ret["states"] = $this->gamestate->machinestates;
         return $ret;
     }

     public function __destruct()
     {
         $this->conn->close();
     }

     private function loadPlayersUIInfos()
     {
         $sql =
             "SELECT player_id, player_name, player_color, player_no, player_is_multiactive FROM player order by player_no";
         return $this->getCollectionFromDB($sql);
     }

     public function loadPlayersBasicInfos()
     {
         $sql =
             "SELECT player_id, player_name, player_color, player_no FROM player order by player_no";
         return $this->getCollectionFromDB($sql);
     }

     function getPlayerRelativePositions()
     {
         $result = [];

         $players = self::loadPlayersBasicInfos();
         $nextPlayer = self::createNextPlayerTable(array_keys($players));

         $current_player = self::getCurrentPlayerId();

         if (!isset($nextPlayer[$current_player])) {
             // Spectator mode: take any player for south
             $player_id = $nextPlayer[0];
         } else {
             // Normal mode: current player is on south
             $player_id = $current_player;
         }
         $result[$player_id] = 0;

         for ($i = 1; $i < count($players); $i++) {
             $player_id = $nextPlayer[$player_id];
             $result[$player_id] = $i;
         }
         return $result;
     }

     function createNextPlayerTable($players)
     {
         $sql = "SELECT player_no, player_id FROM player order by player_no";
         $players = $this->getCollectionFromDB($sql);
         $nexts = [];
         foreach ($players as $player) {
             $next = $player["player_no"] + 1;
             if ($next > count($players)) {
                 $next = 1;
             }
             $nexts[$player["player_id"]] = intval(
                 $players[$next]["player_id"]
             );
         }
         return $nexts;
     }

     function getNextPlayerTable()
     {
         $sql = "SELECT player_no, player_id FROM player order by player_no";
         $players = $this->getCollectionFromDB($sql);
         $nexts = [];
         foreach ($players as $player) {
             $next = $player["player_no"] + 1;
             if ($next > count($players)) {
                 $next = 1;
             }
             $nexts[$player["player_id"]] = intval(
                 $players[$next]["player_id"]
             );
         }
         $nexts[0] = intval(array_shift($players)["player_id"]);
         return $nexts;
     }

     function getPrevPlayerTable()
     {
         $sql = "SELECT player_no, player_id FROM player order by player_no";
         $players = $this->getCollectionFromDB($sql);
         $nexts = [];
         foreach ($players as $player) {
             $next = $player["player_no"] - 1;
             if ($next <= 0) {
                 $next = count($players);
             }
             $nexts[$player["player_id"]] = intval(
                 $players[$next]["player_id"]
             );
         }
         $nexts[0] = intval(end($players)["player_id"]);
         return $nexts;
     }

     function getPlayerAfter($player_id)
     {
         return $this->getNextPlayerTable()[$player_id];
     }

     function getPlayerBefore($player_id)
     {
         return $this->getPrevPlayerTable()[$player_id];
     }

     function getCurrentPlayerId()
     {
         return $this->currentPlayer;
     }

     function getActivePlayerId()
     {
         return $this->getGameStateValue("activePlayerId");
     }

     function getPlayersNumber()
     {
         $sql = "SELECT count(*) FROM player";
         return $this->getUniqueValueFromDB($sql);
     }

     function getActivePlayerName()
     {
         $sql =
             "SELECT player_name FROM player where player_id=" .
             $this->getActivePlayerId();
         return $this->getUniqueValueFromDB($sql);
     }
     function getCurrentPlayerName()
     {
         $sql =
             "SELECT player_name FROM player where player_id=" .
             $this->getCurrentPlayerId();
         return $this->getUniqueValueFromDB($sql);
     }
     function getCurrentPlayerColor()
     {
         $sql =
             "SELECT player_color FROM player where player_id=" .
             $this->getCurrentPlayerId();
         return $this->getUniqueValueFromDB($sql);
     }

     function isCurrentPlayerZombie()
     {
         $sql =
             "SELECT player_zombie FROM player where player_id=" .
             $this->getCurrentPlayerId();
         return $this->getUniqueValueFromDB($sql);
     }

     function getCurrentStateId()
     {
         return $this->getGameStateValue("currentState");
     }

     function loadFile($filename)
     {
         // Temporary variable, used to store current query
         $templine = "";
         // Read in entire file
         $lines = file($filename);
         // Loop through each line
         foreach ($lines as $line) {
             // Skip it if it's a comment
             if (substr($line, 0, 2) == "--" || $line == "") {
                 continue;
             }

             // Add this line to the current segment
             $templine .= $line;
             // If it has a semicolon at the end, it's the end of the query
             if (substr(trim($line), -1, 1) == ";") {
                 // Perform the query
                 $this->conn->query($templine) or
                     (print 'Error performing query \'<strong>' .
                         $templine .
                         '\': ' .
                         $this->conn->error .
                         "<br /><br />");
                 // Reset temp variable to empty
                 $templine = "";
             }
         }
     }

     function stGameSetup()
     {
         $players = [];
         $players[2317679] = [
             "player_no" => 1,
             "player_id" => 2317679,
             "player_canal" => "5ee02c466b5611e99d3379755f289b56",
             "player_name" => "Mistergos0",
             "player_avatar" => "",
         ];
         $players[2317680] = [
             "player_no" => 2,
             "player_id" => 2317680,
             "player_canal" => "5ee02c466b5611e99d3379755f289b56",
             "player_name" => "Mistergos1",
             "player_avatar" => "",
         ];
         $players[2317681] = [
             "player_no" => 3,
             "player_id" => 2317681,
             "player_canal" => "5ee02c466b5611e99d3379755f289b56",
             "player_name" => "Mistergos2",
             "player_avatar" => "",
         ];

         $this->lbgaSetDefaultOptions();

         $this->setupNewGame($players);
         $this->gamestate->nextState("");
     }

     function enterState()
     {
         $data = $this->getMediumDatas();
         $this->notifyAllPlayers("bg_onEnteringState", "", $data);
         $state = $this->gamestate->state();
         if (isset($state["action"])) {
             $mname = $state["action"];
             $ret["action"] = $this->$mname();
         }
     }

     function initTable()
     {
         $result = $this->conn->query("SHOW TABLES LIKE 'player'");
         if ($result->num_rows > 0) {
             if (php_sapi_name() == "cli") {
                 echo "*** Skipping database initialization...\n";
             }
         } else {
             if (php_sapi_name() == "cli") {
                 echo "*** Initializing database...\n";
             }
             $this->loadFile(
                 APP_BASE_PATH . "/module/table/empty_database.sql"
             );
             $this->loadFile(
                 APP_BASE_PATH . "/" . $this->getGameName() . "/dbmodel.sql"
             );
             $this->setGameStateInitialValue("activePlayerId", 0);
             $this->setGameStateInitialValue("moveId", 1);
             $this->setGameStateInitialValue("currentState", 1);
             $this->enterState();
             $this->saveState();
         }
     }

     /*
      * GAME STATE
      */

     public function initGameStateLabels($array)
     {
         $this->gameStateLabels = array_merge($this->gameStateLabels, $array);
     }

     function reattributeColorsBasedOnPreferences()
     {
     }

     function reloadPlayersBasicInfos()
     {
     }

     function getGameStateValue($key)
     {
         $keyint = $this->gameStateLabels[$key];
         return $this->getUniqueValueFromDB(
             "select global_value from global where global_id = " . $keyint
         );
     }

     function setGameStateInitialValue($key, $value)
     {
         $keyint = $this->gameStateLabels[$key];
         $this->lbgaSetGameStateInitialValue($keyint, $value);
     }

     private function lbgaSetGameStateInitialValue($keyint, $value)
     {
         $this->DbQuery(
             "INSERT INTO `global`(`global_id`, `global_value`) VALUES (" .
                 $keyint .
                 "," .
                 $value .
                 ")"
         );
     }

     function setGameStateValue($key, $value)
     {
         $keyint = $this->gameStateLabels[$key];
         $this->DbQuery(
             "INSERT INTO global (global_id, global_value) values({$keyint},{$value}) ON DUPLICATE KEY UPDATE global_value={$value}"
         );
     }

     function initStat($type, $key, $value, $player_id = null)
     {
         if (!is_null($player_id)) {
             throw new \feException('$player_id parameter not supported');
         }
         if ($type !== "player" && $type !== "table") {
             throw new \feException('Stat type must be "player" or "table".');
         }

         $id = $this->stats_type[$type][$key]["id"];
         if (!is_int($id)) {
             echo "*** stat does not have integer ID\n";
             // XXX: Should add an "internal LBGA error" exception type.
             throw new \feException('Stat must have an integer ID.');
         }

         if ($type == "player") {
             $players = $this->loadPlayersBasicInfos();
             foreach ($players as $player) {
                 $this->DbQuery(
                     "INSERT INTO `stats`(`stats_type`, `stats_player_id`, `stats_value`) VALUES (" .
                         $id .
                         "," .
                         $player["player_id"] .
                         "," .
                         $value .
                         ")"
                 );
             }
         } else {
             $this->DbQuery(
                 "INSERT INTO `stats`(`stats_type`, `stats_player_id`, `stats_value`) VALUES (" .
                     $id .
                     ",NULL," .
                     $value .
                     ")"
             );
         }
     }

     function incStat($delta, $name, $player_id = null)
     {
         $type = $player_id == null ? "table" : "player";
         $id = $this->stats_type[$type][$name]["id"];
         if ($player_id == null) {
             $player_id = " NULL ";
         }
         $this->DbQuery(
             "UPDATE `stats` set stats_value = stats_value+" .
                 $delta .
                 " where  stats_type = " .
                 $id .
                 " and stats_player_id=" .
                 $player_id
         );
     }

     function setStat($value, $name, $player_id = null)
     {
         $type = $player_id == null ? "table" : "player";
         $id = $this->stats_type[$type][$name]["id"];
         if ($player_id == null) {
             $player_id = " NULL ";
         }
         $this->DbQuery(
             "UPDATE `stats` set stats_value = " .
                 $value .
                 " where  stats_type = " .
                 $id .
                 " and stats_player_id=" .
                 $player_id
         );
     }

     function getStat($name, $player_id = null)
     {
         $type = $player_id == null ? "table" : "player";
         $id = $this->stats_type[$type][$name]["id"];
         if ($player_id == null) {
             $player_id = " NULL ";
         }
         return $this->getUniqueValueFromDB(
             "select stats_value from stats where stats_type = " .
                 $id .
                 " and stats_player_id=" .
                 $player_id
         );
     }

     function incGameStateValue($value_label, $increment)
     {
         $keyint = $this->gameStateLabels[$value_label];
         $this->DbQuery(
             "UPDATE `global` set global_value = global_value+" .
                 $increment .
                 " where  global_id = " .
                 $keyint
         );
     }

     function checkAction($actionName, $bThrowException = true)
     {
         if (
             in_array(
                 $this->getCurrentPlayerId(),
                 $this->gamestate->getActivePlayerList()
             ) &&
             $this->gamestate->checkPossibleAction($actionName, false)
         ) {
             if ($bThrowException) {
                 $state = $this->gamestate->state();
                 throw new feException(
                     'Impossible action "' .
                         $actionName .
                         '" at this state "' .
                         $state["name"] .
                         '"'
                 );
             } else {
                 return false;
             }
         }
         return true;
     }

     protected function giveExtraTime($player_id, $specific_time = null)
     {
     }

     function saveState()
     {
         $moveId = $this->getGameStateValue("moveId");
         $players = $this->loadPlayersBasicInfos();
         $prevCurrentPlayerId = $this->currentPlayer;
         foreach ($players as $player) {
             $this->currentPlayer = $player["player_id"];
             $sql =
                 "INSERT INTO `replay`(`replay_move_id`, `replay_player_id`,`replay_gamedatas` ) VALUES (" .
                 $moveId .
                 "," .
                 $this->getCurrentPlayerId() .
                 ',\'' .
                 json_encode($this->getAllDatas()) .
                 '\')';
             $this->DbQuery($sql);
         }
         $this->currentPlayer = $prevCurrentPlayerId;
     }

     function _($text)
     {
         return $text;
     }

     /**
      * COMMUNICATION
      */

     public function doAction($gameServer, $params)
     {
         $name = $params["bgg_actionName"];
         if ($name == "bg_game_debugsave") {
             $this->saveDatabase();
         } else {
             $this->gameServer = $gameServer;
             $this->currentPlayer = intval($params["bgg_player_id"]);

             $action = "action_" . $this->getGameName();
             $act = new $action();
             $act->game = $this;
             $act->params = $params;

             $act->$name();

             $this->setGameStateValue(
                 "moveId",
                 $this->getGameStateValue("moveId") + 1
             );
             $this->saveState();
         }
     }

     /**
      * Send a notification to all players of the game.
      *
      * @param string $notification_type A string that defines the type of your notification. Your game interface Javascript logic will use this to know what is the type of the received notification (and to trigger the corresponding method).
      * @param string $notification_log  A string that defines what is to be displayed in the game log. You can use an empty string here (""). In this case, nothing is displayed in the game log. If you define a real string here, you should use "clienttranslate" method to make sure it can be translate. You can use arguments in your notification_log strings, that refers to values defines in the "notification_args" argument (see below). NB: Make sure you only use single quotes ('), otherwise PHP will try to interpolate the variable and will ignore the values in the args array. Note: you CAN use some HTML inside your notification log, and it is working. However: _ pay attention to keep the log clear. _ try to not include some HTML tags inside the "clienttranslate" method, otherwise it will make the translators work more difficult. You can use a notification argument instead, and provide your HTML through this argument.
      * @param array  $notification_args The arguments of your notifications, as an associative array. This array will be transmitted to the game interface logic, in order the game interface can be updated.
      */
     function notifyAllPlayers(
         $notification_type,
         $notification_log,
         $notification_args
     ) {
         $notif = [];
         $players = $this->loadPlayersBasicInfos();
         $notif["gamelog_id"] = $this->getUniqueValueFromDB(
             "select max(gamelog_id)+1 from gamelog"
         );
         $notif["args"] = $notification_args;
         $notif["notification_type"] = $notification_type;
         $notif["notification_log"] = $notification_log;
         $notif["gamelog_move_id"] = $this->getGameStateValue("moveId");
         $data = json_encode($notif);
         if (isset($this->gameServer)) {
             foreach ($players as $player) {
                 $this->gameServer->notifPlayer($player["player_id"], $data);
             }
         }

         $sql =
             "INSERT INTO `gamelog`(`gamelog_move_id`, `gamelog_private`,`gamelog_time`,`gamelog_player`,`gamelog_current_player`,`gamelog_notification` ) VALUES (" .
             $this->getGameStateValue("moveId") .
             ",0,NOW(),NULL," .
             $this->getCurrentPlayerId() .
             ',\'' .
             $data .
             '\')';
         $this->DbQuery($sql);
     }

     /**
      * Same as above notifyAllPlayers, except that the notification is sent to one player only.
      * This method must be used each time some private information must be transmitted to a player.
      * Important: the variable for player name must be ${player_name} in order to be highlighted with the player color in the game log
      *
      * @param $player_id
      * @param $notification_type
      * @param $notification_log
      * @param $notification_args
      */
     function notifyPlayer(
         $player_id,
         $notification_type,
         $notification_log,
         $notification_args
     ) {
         $notif = [];
         $notif["gamelog_id"] = $this->getUniqueValueFromDB(
             "select max(gamelog_id)+1 from gamelog"
         );
         $notif["gamelog_move_id"] = $this->getGameStateValue("moveId");
         $notif["args"] = $notification_args;
         $notif["notification_type"] = $notification_type;
         $notif["notification_log"] = $notification_log;
         $data = json_encode($notif);
         if (isset($this->gameServer)) {
             $this->gameServer->notifPlayer($player_id, $data);
         }

         $sql =
             "INSERT INTO `gamelog`(`gamelog_move_id`, `gamelog_private`,`gamelog_time`,`gamelog_player`,`gamelog_current_player`,`gamelog_notification` ) VALUES (" .
             $this->getGameStateValue("moveId") .
             ",0,NOW()," .
             $player_id .
             "," .
             $this->getCurrentPlayerId() .
             ',\'' .
             $data .
             '\')';
         $this->DbQuery($sql);
     }

     function activeNextPlayer()
     {
         $next = $this->getNextPlayerTable()[$this->getActivePlayerId()];
         $this->setGameStateValue("activePlayerId", $next);
     }

     function getGameinfos()
     {
         $gameinfos = [];
         $gameinfos["player_colors"] = [
             "ff0000",
             "008000",
             "0000ff",
             "ffa500",
             "773300",
         ];
         return $gameinfos;
     }
 }
