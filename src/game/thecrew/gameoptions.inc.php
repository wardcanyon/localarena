<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * EmptyGame implementation : © Nicolas Gocel <nicolas.gocel@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * gameoptions.inc.php
 *
 * EmptyGame game options description
 *
 * In this file, you can define your game options (= game variants).
 *
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in emptygame.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

$game_options = [
    100 => [
        "name" => totranslate("Mission"),
        "values" => [
            999 => [
                "name" => totranslate("Campaign"),
                "tmdisplay" => totranslate(
                    "Continue existing campaign or start a new one"
                ),
            ],
            1 => ["name" => totranslate("Mission") . " 1"],
            2 => ["name" => totranslate("Mission") . " 2"],
            3 => ["name" => totranslate("Mission") . " 3"],
            4 => ["name" => totranslate("Mission") . " 4"],
            5 => ["name" => totranslate("Mission") . " 5"],
            6 => ["name" => totranslate("Mission") . " 6"],
            7 => ["name" => totranslate("Mission") . " 7"],
            8 => ["name" => totranslate("Mission") . " 8"],
            9 => ["name" => totranslate("Mission") . " 9"],
            10 => ["name" => totranslate("Mission") . " 10"],
            11 => ["name" => totranslate("Mission") . " 11"],
            12 => ["name" => totranslate("Mission") . " 12"],
            13 => ["name" => totranslate("Mission") . " 13"],
            14 => ["name" => totranslate("Mission") . " 14"],
            15 => ["name" => totranslate("Mission") . " 15"],
            16 => ["name" => totranslate("Mission") . " 16"],
            17 => ["name" => totranslate("Mission") . " 17"],
            18 => ["name" => totranslate("Mission") . " 18"],
            19 => ["name" => totranslate("Mission") . " 19"],
            20 => ["name" => totranslate("Mission") . " 20"],
            21 => ["name" => totranslate("Mission") . " 21"],
            22 => ["name" => totranslate("Mission") . " 22"],
            23 => ["name" => totranslate("Mission") . " 23"],
            24 => ["name" => totranslate("Mission") . " 24"],
            25 => ["name" => totranslate("Mission") . " 25"],
            26 => ["name" => totranslate("Mission") . " 26"],
            27 => ["name" => totranslate("Mission") . " 27"],
            28 => ["name" => totranslate("Mission") . " 28"],
            29 => ["name" => totranslate("Mission") . " 29"],
            30 => ["name" => totranslate("Mission") . " 30"],
            31 => ["name" => totranslate("Mission") . " 31"],
            32 => ["name" => totranslate("Mission") . " 32"],
            33 => ["name" => totranslate("Mission") . " 33"],
            34 => ["name" => totranslate("Mission") . " 34"],
            35 => ["name" => totranslate("Mission") . " 35"],
            36 => ["name" => totranslate("Mission") . " 36"],
            37 => ["name" => totranslate("Mission") . " 37"],
            38 => ["name" => totranslate("Mission") . " 38"],
            39 => ["name" => totranslate("Mission") . " 39"],
            40 => ["name" => totranslate("Mission") . " 40"],
            41 => ["name" => totranslate("Mission") . " 41"],
            42 => ["name" => totranslate("Mission") . " 42"],
            43 => ["name" => totranslate("Mission") . " 43"],
            44 => ["name" => totranslate("Mission") . " 44"],
            45 => ["name" => totranslate("Mission") . " 45"],
            46 => ["name" => totranslate("Mission") . " 46"],
            47 => ["name" => totranslate("Mission") . " 47"],
            48 => ["name" => totranslate("Mission") . " 48"],
            49 => ["name" => totranslate("Mission") . " 49"],
            50 => ["name" => totranslate("Mission") . " 50"],
        ],
    ],

    101 => [
        "name" => totranslate("Challenge mode for Three"),
        "values" => [
            1 => ["name" => totranslate("Off")],
            2 => ["name" => totranslate("On")],
        ],
        "startcondition" => [
            2 => [
                [
                    "type" => "maxplayers",
                    "value" => 3,
                    "message" => totranslate(
                        "Challenge mode for Three is only for 3 players."
                    ),
                    "gamestartonly" => true,
                ],
            ],
        ],
    ],
];
