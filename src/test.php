 <?php
 define("APP_BASE_PATH", "/src/");
 define("APP_GAMEMODULE_PATH", "/src/");

 set_include_path(get_include_path() . PATH_SEPARATOR . APP_GAMEMODULE_PATH);

 include "emppty/emppty.game.php";

 echo "*** Constructing game class...\n";
 $game = new emppty();
 echo "*** Initializing table...\n";
 $game->initTable();
 echo "*** Ready!\n";

 $game->actColor("blue");


?>
