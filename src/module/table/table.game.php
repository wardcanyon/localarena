 <?php
 require_once APP_BASE_PATH . "module/table/feException.php";
 require_once APP_BASE_PATH . "module/table/BgaVisibleSystemException.php";
 require_once APP_BASE_PATH . "module/table/GameState.php";
 require_once APP_BASE_PATH . "module/table/APP_GameAction.php";
 require_once APP_BASE_PATH . "module/table/deck.php";
 require_once APP_BASE_PATH . "view/common/util.php";
 require_once APP_BASE_PATH . "module/LocalArenaContext.php";

 class APP_Object
 {
     function __construct()
     {
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
     public $conn;

     public $gameServer = null;

     function __construct()
     {
         $la_ctx = LocalArenaContext::get();
         $dbname = "table_" . $la_ctx->table_id;

         // These are provided by Docker Compose; see "compose.yaml".
         $this->servername = getenv("DB_HOST");
         $this->username = getenv("DB_USER");
         $this->dbname = $dbname;
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

         // Check connection
         if ($this->conn->connect_error) {
             die("Connection failed: " . $this->conn->connect_error);
         }

         /* Activation du reporting */
         $driver = new mysqli_driver();
         $driver->report_mode = MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_INDEX;

         // Set transaction isolation level so that we can read back
         // changes later in the same transaction.
         $this->conn->query("SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");
     }

     // XXX: This is part of the LOCALARENA API, not the BGA API; it is
     // intended only for internal use.  We should fix its visibility.
     public function log($msg)
     {
         if ($this->gameServer) {
             echo $msg . "\n";
         }
     }

     function getCollectionFromDB(
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

                 if ($bSingleValue) {
                     $key_b = array_keys($row)[1];
                     $value = $row[$key_b];
                 } else {
                     $value = $row;
                 }
                 $ret[$row[$key]] = $value;
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
                 throw new feException("empty results");
             }
         } catch (mysqli_sql_exception $e) {
             var_dump($sql);
             throw $e;
         }
         return $ret;
     }

     /*
       Return an associative array of associative array, from a SQL SELECT query.
       First array level correspond to first column specified in SQL query.
       Second array level correspond to second column specified in SQL query.
       If $bSingleValue = true, keep only third column on result.
     */
     function getDoubleKeyCollectionFromDB(string $sql, bool $bSingleValue=false) {
         $ret = [];
         try {
             if (!($data = $this->conn->query($sql))) {
                 var_dump($this->conn->error);
             }
             $fetch = mysqli_fetch_all($data, MYSQLI_ASSOC);

             foreach ($fetch as $row) {
                 $key_a = array_keys($row)[0];
                 $key_b = array_keys($row)[1];

                 if (!array_key_exists($row[$key_a], $ret)) {
                     $ret[$row[$key_a]] = [];
                 }

                 if ($bSingleValue) {
                     $key_c = array_keys($row)[2];
                     $value = $row[$key_c];
                 } else {
                     $value = $row;
                 }
                 $ret[$row[$key_a]][$row[$key_b]] = $value;
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
                 throw new feException("empty results");
             }

             $ret = mysqli_fetch_assoc($data);
         } catch (Exception $e) {
             var_dump($sql);
             throw $e;
         }
         return $ret;
     }

     function getUniqueValueFromDB($sql, $low_priority_select = false)
     {
         $ret = "";
         try {
             if (!($data = $this->conn->query($sql))) {
                 var_dump($this->conn->error);
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

     // N.B.: The "reversi" example uses `mysql_fetch_assoc()` on the
     // return value of this function.
     function DbQuery($sql, $specific_db = null, $bMulti = false)
     {
         //  var_dump($sql);
         try {
             return $this->conn->query(
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

     function DbGetLastId()
     {
         return $this->conn->insert_id;
     }

     // XXX: This isn't part of the interface of this class; it's
     // something added in LOCALARENA.
     private function saveDatabase()
     {
         $dir = "/src/databaseExport/database.sql";
         exec(
             "mysqldump --user={$this->username} --password={$this->password} --host={$this->servername} {$this->dbname} --result-file={$dir} 2>&1",
             $output
         );
     }

     // XXX: This isn't part of the interface of this class; it's
     // something added in LOCALARENA.
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
     public $game_preferences;
     public $custom_only;

     function __construct()
     {
         parent::__construct();

         include $this->getGameName() . "/stats.inc.php";
         $this->stats_type = $stats_type;

         include $this->getGameName() . "/gameoptions.inc.php";
         $this->game_options = $game_options;
         $this->game_preferences = $game_preferences;
         $this->custom_only = $custom_only;

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

     function localarenaSetDefaultOptions()
     {
         foreach ($this->game_options as $option_id => $option_desc) {
             echo '** set default options; id = ' . $option_id . ' and desc = ' . print_r($option_desc, true) . "\n";
             // N.B.: "default" is optional; if not given, the first
             // option listed is the default.
             $this->localarenaSetGameStateInitialValue(
                 $option_id,
                 $option_desc["default"] ?? array_key_first($option_desc['values']),
             );
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

     public function localarenaGetGameName()
     {
         return $this->getGameName();
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

     // Returns the state descriptor, plus:
     // - active_player as a PlayerIdString
     // - multiactive as an array of PlayerIdString
     // - "reflexion"
     // - args is the result of calling the args function rather than its name
     // - id (the key that the state has in the states.inc.php array)
     function getStateForClient(bool $includeMultiactive)
     {
         $ret = $this->gamestate->state();

         $ret["id"] = $this->getCurrentStateId();

         $ret['args'] = $this->renderPrivateData($this->getCurrentPlayerId(), $this->renderStateArgs($ret));

         // This is always set, even when we're in a multiactive state
         // and it should have no effect.  The client needs to be
         // smart enough to ignore it in those situations.
         $ret["active_player"] = $this->getActivePlayerId();

         if ($this->gamestate->state()["type"] === "multipleactiveplayer") {
             // This is always empty when the message is being sent in
             // response to a state transition (if the state is
             // multiactive; absent otherwise).  There will be a
             // subsequent "gameStateMultipleActiveUpdate" message
             // with actual values.

             if ($includeMultiactive) {
                 $ret["multiactive"] = $this->gamestate->getActivePlayerList();
             } else {
                 $ret["multiactive"] = [];
             }
         }

         // TODO: We don't support this feature yet.
         $ret["reflexion"] = null;

         return $ret;
     }

     function getPrefsForClient() {
         $prefs = $this->game_preferences;

         $prefs[200] = [
             'name' => 'Display tooltips',
             'needReload' => false,
             'generic' => true,
             'value'=> 0,
             'values'=> [
                 0 => [ 'name'=> 'Enabled' ],
                 1 => [ 'name'=> 'Disabled' ],
             ],
         ];

         foreach ($prefs as $pref_id => $pref) {
             if (!array_key_exists('value', $pref)) {
                 $prefs[$pref_id]['value'] = array_key_first($pref['values']);
             }
         }

         return $prefs;
     }

     function notify_gameStateChange(bool $includeMultiactive)
     {
         echo "Sending notif: gameStateChange" . "\n";
         $this->notifyAllPlayers(
             "gameStateChange",
             "",
             $this->getStateForClient($includeMultiactive)
         );
     }

     function notify_gameStateMultipleActiveUpdate()
     {
         echo "Sending notif: gameStateMultipleActiveUpdate" . "\n";

         $this->notifyAllPlayers(
             "gameStateMultipleActiveUpdate",
             "",
             // An array of `PlayerIdString`s identifying the players
             // who are currently multiactive.
             $this->gamestate->getActivePlayerList()
         );
     }

     function getMediumDatas()
     {
         $ret = [];
         $ret["id"] = $this->getCurrentStateId();

         // This is always set, even when we're in a multiactive state
         // and it should have no effect.  The client needs to be
         // smart enough to ignore it in those situations.
         $ret["active_player"] = $this->getActivePlayerId();

         if ($this->gamestate->state()["type"] === "multipleactiveplayer") {
             $ret["multiactive"] = $this->gamestate->getActivePlayerList();
         }

         $ret["players"] = $this->loadPlayersUIInfos();
         $state = $this->gamestate->state();
         $ret['args'] = $this->renderStateArgs($state);
         return $ret;
     }

     function renderStateArgs($state) {
         if (!isset($state["args"])) {
             return null;
         }

         $mname = $state["args"];
         $args = $this->$mname();

         if (array_key_exists('_private', $args)) {
             $private_args = $args['_private'];

             // $private_args may either contain the single key
             // "active", or may contain player ID strings as keys.
             // It cannot contain both.

             if (array_key_exists('active', $private_args)) {
                 if (count($private_args) != 1) {
                     $this->strictError('If _private args contain the "active" key, that must be the only key.');
                 }
                 $args['_private'] = [
                     $this->getActivePlayerId() => $private_args['active'],
                 ];

                 // if ($this->getCurrentPlayerId() == $this->getCurrentActiveId()) {
                 // }
             } else {
                 // XXX: Validate that the other keys are all valid
                 // player IDs as strings here, before we commit the
                 // message.
             }
         }

         return $args;
     }

     function strictError($msg) {
         // XXX: no-op; but in strict mode, this should throw an exception
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
                 $this->getAllDatas(),
                 $this->getMediumDatas(),
             );
         }

         $ret["states"] = $this->gamestate->machinestates;

         // XXX: This duplicates some information; it's used in our
         // bootstrapping call to `completesetup()` on the client,
         // after initial page load.
         $ret["gameState"] = $this->getStateForClient(
             /*includeMultiactive=*/ true
         );

         // N.B.: BGA itself renders preferences into the served page
         // and has code there that directly sets `gameui.prefs`;
         // we're reusing the gamedatas mechanism here, which is
         // similar but not identical.
         $ret['prefs'] = $this->getPrefsForClient();

         return $ret;
     }

     public function __destruct()
     {
         $this->conn->close();
     }

     private function loadPlayersUIInfos()
     {
         $ret = $this->getCollectionFromDB("SELECT player_id, player_name, player_color, player_no, player_is_multiactive FROM player ORDER BY player_no");
         // XXX: The "reversi" example expects the property "color",
         // not "player_color".  We should inspect actual BGA output.
         //
         // XXX: Can we use refs here to mutate $player?
         foreach ($ret as $player_id => $player) {
             $ret[$player_id]['color'] = $player['player_color'];
         }
         return $ret;
     }

     public function loadPlayersBasicInfos()
     {
         $sql =
             "SELECT player_id, player_name, player_color, player_no FROM player ORDER BY player_no";
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
         $sql = "SELECT player_no, player_id FROM player ORDER BY player_no";
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

     // XXX: Does this function (and its wrappers) need to also accept
     // player-ID-as-string values?
     //
     // XXX: Double-check the exact return values/types of these functions.
     //
     private function getPlayerRowById(int $player_id)
     {
         return $this->getObjectFromDB('SELECT * FROM `player` WHERE `player_id` = '.$player_id);
     }

     function getPlayerNameById(int $player_id): string {
         $row = $this->getPlayerRowById($player_id);
         return $row['player_name'];
     }

     function getPlayerNoById(int $player_id): int {
         return intval($this->getPlayerRowById($player_id)['player_no']);
     }

     function getPlayerColorById(int $player_id): int {
         return $this->getPlayerRowById($player_id)['player_color'];
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

     function isSpectator()
     {
         // XXX: We don't support spectators yet.
         return false;
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
         $first_player_id = 2317679;

         $players = [];
         for ($i = 0; $i < LOCALARENA_PLAYER_COUNT; $i++) {
             $player_id = $first_player_id + $i;
             $players[$player_id] = [
                 'player_no' => $i + 1,
                 'player_id' => $player_id,
                 'player_canal' => '5ee02c466b5611e99d3379755f289b56',
                 'player_name' => LOCALARENA_PLAYER_NAME_STEM . $i,
                 'player_avatar' => '',
             ];
         }

         $this->localarenaSetDefaultOptions();

         $this->setupNewGame($players);
         $this->gamestate->nextState("");
     }

     function enterState()
     {
         $state = $this->gamestate->state();

         echo "enterState(): name=" .
             $state["name"] .
             " type=" .
             $state["type"] .
             "\n";

         $this->notify_gameStateChange(/*includeMultiactive=*/ false);
         if ($state["type"] == "multipleactiveplayer") {
             $this->notify_gameStateMultipleActiveUpdate();
         }

         echo "enterState(): done sending notifs" . "\n";

         if (isset($state["action"])) {
             $mname = $state["action"];
             $this->$mname();
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
         $this->localarenaSetGameStateInitialValue($keyint, $value);
     }

     private function localarenaSetGameStateInitialValue($keyint, $value)
     {
         // echo 'localarenaSetGameStateInitialValue(): key=' . $keyint . ' value=' . $value . "\n";
         // $this->DbQuery(
         //     "INSERT INTO `global`(`global_id`, `global_value`) VALUES (" .
         //         $keyint .
         //         "," .
         //         $value .
         //         ")"
         // );

         // XXX: The query above does not allow duplicate calls.  This
         // causes trouble when a game calls
         // `setGameStateInitialValue()` during setup, because right
         // now LocalArena always calls `setGameStateInitialValue()`
         // to set options to defaults.  The BGA "hearts" example does
         // this.
         //
         // Another option here is probably to wait until after the
         // game's setup code runs, and then fill in defaults for any
         // options that aren't set.
         $this->DbQuery(
             "INSERT INTO global (global_id, global_value) values({$keyint},{$value}) ON DUPLICATE KEY UPDATE global_value={$value}"
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
             // XXX: Should add an "internal LocalArena error" exception type.
             throw new \feException("Stat must have an integer ID.");
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
         $this->gameServer = $gameServer;
         $this->currentPlayer = intval($params["bgg_player_id"]);

         $name = $params["bgg_actionName"];
         if ($name == "bg_game_debugsave") {
             $this->saveDatabase();
         } else {
             if (!$this->conn->begin_transaction()) {
                 // XXX: Error type
                 throw new \feException("Unable to begin transaction.");
             }
             $prev_last_gamelog_id = $this->getUniqueValueFromDB(
                 "SELECT MAX(gamelog_id) FROM `gamelog`"
             );

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

             try {
                 $this->conn->commit();
             } catch (mysqli_sql_exception $e) {
                 $this->conn->rollback();
                 throw $e;
             }

             $this->sendCommittedNotifs($prev_last_gamelog_id);
         }
     }

     // N.B.: $data may be null, or may not be an associative array at
     // all; for example, the "hearts" example sends a string.
     function renderPrivateData($player_id, $data) {
         if (is_array($data)) {
             if (array_key_exists('_private', $data)) {
                 $private_args = $data['_private'];
                 unset($data['_private']);
                 if (array_key_exists($player_id, $private_args)) {
                     $data['_private'] = $private_args[$player_id];
                 }
             }
         }
         return $data;
     }

     // Sends notifs for gamelog entries with IDs greater than
     // $prev_last_gamelog_id to the appropriate player(s).
     function sendCommittedNotifs($prev_last_gamelog_id)
     {
         if (!isset($this->gameServer)) {
             // XXX: In the existing code, there were guards for
             // `isset($this->gameServer)`.  When would that not be
             // true?
             return;
         }

         $players = $this->loadPlayersBasicInfos();
         $entries = $this->getCollectionFromDB(
             "SELECT * FROM `gamelog` WHERE `gamelog_id` > " .
                 $prev_last_gamelog_id .
                 " ORDER BY `gamelog_id` ASC"
         );

         $sendNotif = function($player_id, $data) {
             return $this->gameServer->notifPlayer($player_id, $this->renderPrivateData($player_id, $data));
         };

         foreach (array_values($entries) as $entry) {
             $data = $entry["gamelog_notification"];
             if ($entry["gamelog_player"] !== null) {
                 $sendNotif(
                     $entry["gamelog_player"],
                     $data,
                 );
             } else {
                 foreach ($players as $player) {
                     $sendNotif(
                         $player["player_id"],
                         $data
                     );
                 }
             }
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
         // N.B.: This function and `notifyPlayer()` do not actually
         // send notifs; they add entries to the gamelog.  After the
         // transaction commits, we send notifs to the appropriate
         // player(s).

         $notif = [];
         $notif["gamelog_id"] = $this->getUniqueValueFromDB(
             "select max(gamelog_id)+1 from gamelog"
         );
         $notif["args"] = $notification_args;
         $notif["notification_type"] = $notification_type;
         $notif["notification_log"] = $notification_log;
         $notif["gamelog_move_id"] = $this->getGameStateValue("moveId");
         $data = json_encode($notif);

         $sql =
             "INSERT INTO `gamelog`(`gamelog_move_id`, `gamelog_private`,`gamelog_time`,`gamelog_player`,`gamelog_current_player`,`gamelog_notification` ) VALUES (" .
             $this->getGameStateValue("moveId") .
             ",0,NOW(),NULL," .
             $this->getCurrentPlayerId() .
             ',\'' .
             $this->escapeStringForDb($data) .
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

     // Makes the next player active; returns that player's ID.
     function activeNextPlayer(): int
     {
         $next = $this->getNextPlayerTable()[$this->getActivePlayerId()];
         $this->setGameStateValue("activePlayerId", $next);
         return $next;
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
