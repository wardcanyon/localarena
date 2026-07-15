<?php

// Opening -- and, on first use, initializing -- the shared
// `localarena` metadata database, which holds the table registry (see
// `TableManager`) and the legacy-games data (see
// `module/table/LocalArenaLegacy.php`).
//
// The schema lives in `schema.sql` and is applied in full whenever the
// database is found uninitialized (no `table` table yet).  LocalArena
// does not do schema migrations: if the schema changes, recreate the
// database (e.g. by dropping the Docker volume).

require_once APP_GAMEMODULE_PATH . 'module/db_config.php';

function localarenaApplyMetadataSchemaFile(mysqli $conn, string $filename): void
{
  // Temporary variable, used to store current query
  $templine = '';
  // Loop through each line
  foreach (file($filename) as $line) {
    // Skip it if it's a comment
    if (substr($line, 0, 2) == '--' || $line == '') {
      continue;
    }

    // Add this line to the current segment
    $templine .= $line;
    // If it has a semicolon at the end, it's the end of the query
    if (substr(trim($line), -1, 1) == ';') {
      // Perform the query
      $conn->query($templine) or
        (print 'Error performing query \'<strong>' . $templine . '\': ' . $conn->error . '<br /><br />');
      // Reset temp variable to empty
      $templine = '';
    }
  }
}

function localarenaOpenMetadataDb(): mysqli
{
  $conn = new mysqli(getenv('DB_HOST'), getenv('DB_USER'), localarena_db_password(), 'localarena', localarena_db_port());
  if ($conn->connect_error) {
    throw new \Exception('Could not connect to the localarena metadata database: ' . $conn->connect_error);
  }

  // Set transaction isolation level so that we can read back
  // changes later in the same transaction.
  $conn->query('SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

  $result = $conn->query("SHOW TABLES LIKE 'table'");
  if ($result->num_rows == 0) {
    if (php_sapi_name() == 'cli') {
      echo "*** LocalArena metadata database requires initialization...\n";
    }
    localarenaApplyMetadataSchemaFile($conn, APP_GAMEMODULE_PATH . 'module/tablemanager/schema.sql');
  }

  return $conn;
}
