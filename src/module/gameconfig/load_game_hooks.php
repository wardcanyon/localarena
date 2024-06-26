<?php declare(strict_types=1);

// echo '*** XXX: load_game_hooks.php: requiring LocalArenaGameConfig.php' . "\n";
require_once 'LocalArenaGameConfig.php';

// echo '*** XXX: load_game_hooks.php: attempting to require composer autoload.php' . "\n";
$loader = require_once APP_GAMEMODULE_PATH . '/vendor/autoload.php';

// echo '*** XXX: load_game_hooks.php: okay, done with requires' . "\n";

use \Opis\JsonSchema\Validator;

function localarenaLoadGameHooks($localarena_config_path) {
    $localarenaGameConfig = new LocalArenaGameConfig();

    $validator = new Validator();

    require_once $localarena_config_path;
}
