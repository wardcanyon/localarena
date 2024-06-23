<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Chakra implementation : © Nicolas Gocel <nicolas.gocel@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * states.inc.php
 *
 * Chakra game states description
 *
 */

$machinestates = [
    // The initial state. Please do not modify.
    1 => [
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => ["" => 2],
    ],

    2 => [
        "name" => "stNoop",
        "description" => clienttranslate(
            'There is nothing that ${actplayer} can do.',
        ),
        "descriptionmyturn" => clienttranslate(
            'There is nothing that ${you} can do.',
        ),
        "type" => "activeplayer",
        "possibleactions" => [
        ],
        "transitions" => [
            "tEndGame" => 99,
        ],
    ],

    // Final state.
    // Please do not modify (and do not overload action/args methods).
    99 => [
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd",
    ],
];
