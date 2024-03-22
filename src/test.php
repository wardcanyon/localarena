 <?php

     define("APP_BASE_PATH",'/src/');
     define("APP_GAMEMODULE_PATH",'/src/');

set_include_path(get_include_path() . PATH_SEPARATOR . APP_GAMEMODULE_PATH);

     include('emppty/emppty.game.php');

     $game = new emppty();
     $game->actColor( 'blue');


    ?>
