<?php

require_once APP_GAMEMODULE_PATH . 'module/db_config.php';
require_once APP_GAMEMODULE_PATH . 'localarena_config.inc.php';
require_once APP_GAMEMODULE_PATH . 'module/LocalArenaContext.php';

echo '*** XXX: requiring load_game_hooks.php...' . "\n";
require_once APP_GAMEMODULE_PATH . 'module/gameconfig/load_game_hooks.php';

require_once 'TableParams.php';
require_once APP_GAMEMODULE_PATH . 'module/tablemanager/metadata_db.php';

use \LocalArena\TableParams;

class TableManager
{
  private $conn;

    private $test_table_classes = [];

  public function __construct()
  {
    // Open the `localarena` metadata database (created by the MySQL
    // container), initializing it from schema.sql if it is still
    // empty.
    $this->conn = localarenaOpenMetadataDb();
  }

  public static function openDatabase(string $dbname)
  {
    $servername = getenv('DB_HOST');
    $username = getenv('DB_USER');
    $password = localarena_db_password();

    $conn = new mysqli($servername, $username, $password, $dbname, localarena_db_port());

    // Set transaction isolation level so that we can read back
    // changes later in the same transaction.
    $conn->query('SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

    return $conn;
  }

  public static function getTableDatabaseName(int $table_id)
  {
    return 'table_' . $table_id;
  }

  public static function getGameClassName(string $game_name)
  {
    return ucfirst($game_name);
  }

  // Returns a `Table` object, or null if no table with that ID
  // exists.
  public function getTable(int $table_id)
  {
    // XXX: look up table id

    $result = $this->conn->query('SELECT * FROM `table` WHERE `table_id` = ' . $table_id);
    if ($result->num_rows == 0) {
      return null;
    }
    $row = mysqli_fetch_assoc($result);

    $dbname = $row['table_database'];
    // XXX: check for null $dbname

    // XXX: we should do this whenever we enter game code, not
    // here (or at least not only here)
    //
    // We need to do this before instantiating the game class.
    LocalArenaContext::get()->table_id = $table_id;

    if (array_key_exists($table_id, $this->test_table_classes)) {
        $table_class = $this->test_table_classes[$table_id];
        $game = new $table_class($dbname);
    } else {
        require_once LOCALARENA_GAME_PATH . $row['table_game'] . '/' . $row['table_game'] . '.game.php';
        $classname = $this::getGameClassName($row['table_game']);
        $game = new $classname($dbname);

        {
            $localarena_config_path = LOCALARENA_GAME_PATH . $row['table_game'] . '/localarena_config.inc.php';
            if (file_exists($localarena_config_path)) {
                echo '*** Loading game hooks...' . "\n";
                localarenaLoadGameHooks($game->localarena_game_config_, $localarena_config_path);
            } else {
                echo '*** Skipping game hooks (file not found: '.$localarena_config_path.')...' . "\n";
            }
        }
    }

    $game->localarena_table_id = $table_id;

    // NULL (tables created before legacy-scope support) leaves the
    // Table falling back to its default scope, the game name.
    $game->localarena_legacy_scope = $row['table_legacy_scope'] ?? null;

    return $game;
  }

  // Creates a table, assigns it an ID, and returns a `Table`
  // object.
  //
  // This will initialize the new table's database, call
  // `setupNewGame()`, and perform any initial state transitions.
  public function createTable(TableParams $params)
  {
    // A null legacy scope defaults to the empty string: the game's one
    // shared pool of legacy data (as on BGA).  The scope is recorded
    // on the table's registry row.
    $legacy_scope = $params->legacy_scope ?? '';
    $this->conn->query(
      'INSERT INTO `table` (table_game, table_legacy_scope) VALUES ("' .
        $params->game .
        '", "' .
        $this->conn->real_escape_string($legacy_scope) .
        '")'
    );
    $table_id = $this->conn->insert_id;

    $dbname = $this::getTableDatabaseName($table_id);
    $this->conn->query('CREATE DATABASE ' . $dbname);
    $this->conn->query('UPDATE `table` SET `table_database` = "' . $dbname . '" WHERE `table_id` = ' . $table_id);

    if ($params->table_class !== null) {
        $this->test_table_classes[$table_id] = $params->table_class;
    }

    $game = $this->getTable($table_id);
    $game->initTable($params->load_schema_file);

    if ($params->schema_changes !== '') {
        $game->localarenaApplySchema(explode('\n',$params->schema_changes));
    }

    if ($params->enable_undo_savepoints) {
        $game->setUndoSavepointsEnabled(true);
    }

    // Null leaves the table with BGA's own per-request notification
    // size limit, which is what `Table` starts out with.
    if ($params->notif_size_limit !== null) {
        $game->localarenaSetNotifSizeLimit($params->notif_size_limit);
    }

    return $game;
  }
}
