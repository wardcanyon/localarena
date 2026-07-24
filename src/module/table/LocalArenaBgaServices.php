<?php

// The services object available to game code as `$this->bga`.  On
// BGA's modern framework this carries various services; LocalArena
// currently provides the legacy-games API (see LocalArenaLegacy.php).

require_once APP_GAMEMODULE_PATH . 'module/table/LocalArenaLegacy.php';

class LocalArenaBgaServices
{
  public LocalArenaLegacy $legacy;

  public function __construct($table)
  {
    $this->legacy = new LocalArenaLegacy($table);
  }
}
