<?php
/*
 *
 * In this file, you can define your game options (= game variants).
 *
 * Note: All options defined in this file should have a corresponding
 *       "game state labels" with the same ID (see
 *       "initGameStateLabels" in emptygame.game.php).
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

require_once "modules/php/constants.inc.php";

/*
Other possible options:

- Enable/disable heist rewards (gear cards)
- Character order randomization
 */

$game_options = [
    OPTION_CHARACTER_SELECTION => [
        "name" => totranslate("Character selection"),
        "default" => 1,
        "values" => [
            1 => [
                "name" => "Player choice",
            ],
            2 => [
                "name" => "Random",
                "nobeginner" => true,
            ],
        ],
    ],
    OPTION_SUSPICION => [
        "name" => totranslate("Suspicion"),
        "default" => 6,
        "values" => [
            10 => ["name" => "10"],
            9 => ["name" => "9"],
            8 => ["name" => "8"],
            7 => ["name" => "7"],
            6 => ["name" => "6"],
            5 => ["name" => "5"],
            4 => ["name" => "4"],
            3 => ["name" => "3"],
            2 => ["name" => "2"],
            1 => ["name" => "1"],
        ],
    ],
    OPTION_FINALE => [
        "name" => totranslate("Finale"),
        "default" => 99,
        "values" => [
            99 => ["name" => "Random"],
            98 => ["name" => "Random and hidden"],
            1 => ["name" => "(1) Bodyguard"],
            2 => ["name" => "(2) Rescuing Her Ride"],
            3 => ["name" => "(3) Laundry Day"],
            4 => ["name" => "(4) Caught Red Handed"],
            5 => ["name" => "(5) Gravity Boots"],
            6 => ["name" => "(6) Catch a Tiger"],
            7 => ["name" => "(7) Hail Mary"],
            8 => ["name" => "(8) The Raid"],
            9 => ["name" => "(9) Bringing Down the House"],
            10 => ["name" => "(10) Bachelor Party"], // Casing the Joint only
        ],
    ],
    OPTION_MULTIHANDED => [
        // Enabled, disabled; if enabled, players may choose any
        // number of characters so long as total does not exceed 4
        "name" => "Multi-handed",
        "default" => OPTVAL_ENABLED,
        "values" => [
            OPTVAL_DISABLED => [
                "name" => "Disabled",
            ],
            OPTVAL_ENABLED => [
                "name" => "Enabled",
            ],
        ],
        "displaycondition" => [
            [
                // The game requires at least two characters, so
                // disabling multi-handed play doesn't make sense if
                // playing solo.
                "type" => "minplayers",
                "value" => [2, 3, 4],
            ],
        ],
    ],
    OPTION_WALL_PLACEMENT => [
        "name" => "Wall placement",
        "default" => 1,
        "values" => [
            1 => [
                "name" => "Player choice",
            ],
            2 => [
                "name" => "Random",
            ],
            3 => [
                "name" => "Community random",
            ],
        ],
    ],
    OPTION_WALL_REROLLING => [
        "name" => "Wall rerolling",
        "default" => OPTVAL_ENABLED,
        "values" => [
            OPTVAL_DISABLED => [
                "name" => "Disabled",
                "nobeginner" => true,
            ],
            OPTVAL_ENABLED => [
                "name" => "Enabled",
            ],
        ],
    ],
    OPTION_VARIANT_DEAD_DROPS => [
        "name" => "Variant: Dead Drops",
        "default" => OPTVAL_DISABLED,
        "values" => [
            OPTVAL_DISABLED => [
                "name" => "Disabled",
            ],
            OPTVAL_ENABLED => [
                "name" => "Enabled",
                "nobeginner" => true,
            ],
        ],
    ],
    OPTION_VARIANT_CASING_THE_JOINT => [
        "name" => "Variant: Casing the Joint",
        "default" => OPTVAL_DISABLED,
        "values" => [
            OPTVAL_DISABLED => [
                "name" => "Disabled",
            ],
            OPTVAL_ENABLED => [
                "name" => "Enabled",
                "nobeginner" => true,
            ],
        ],
    ],
];

$game_preferences = [];
