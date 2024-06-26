<?php declare(strict_types=1);

require_once 'LocalArenaGameConfig.php';

require_once APP_GAMEMODULE_PATH . '/vendor/autoload.php';

function localarenaLoadGameHooks(&$localarenaGameConfig, $localarena_config_path) {
    require_once $localarena_config_path;

    if (function_exists('localarenaConfigureGame')) {
        localarenaConfigureGame($localarenaGameConfig);
    }
}
