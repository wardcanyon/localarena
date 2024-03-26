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

if (!defined("STATE_TAKE")) {
    define("STATE_TAKE", 2);
    define("STATE_CHANNEL", 3);
    define("STATE_CHECK_FINISH", 4);
    define("STATE_PICK_COLOR", 5);
}

$machinestates = [
    // The initial state. Please do not modify.
    1 => [
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => ["" => 2],
    ],

    STATE_TAKE => [
        "name" => "take",
        "description" => clienttranslate(
            '${actplayer} must receive energy {receive}, channel energy {channel} or meditate {meditate}'
        ),
        "descriptionmyturn" => clienttranslate(
            '${you} must receive energy {receive}, channel energy {channel} or meditate {meditate}'
        ),
        "type" => "activeplayer",
        "args" => "argTake",
        "possibleactions" => ["actTake", "actChannel", "actColor"],
        "transitions" => [
            "next" => STATE_CHECK_FINISH,
            "pickColor" => STATE_PICK_COLOR,
            "finish" => STATE_CHECK_FINISH,
            "channel" => STATE_CHANNEL,
            "zombiePass" => STATE_CHECK_FINISH,
        ],
    ],

    STATE_CHANNEL => [
        "name" => "channel",
        "description" => clienttranslate(
            '${actplayer} must channel its energies'
        ),
        "descriptionmyturn" => clienttranslate(
            '${you} must channel your energies'
        ),
        "type" => "activeplayer",
        "args" => "argChannel",
        "possibleactions" => ["actMove", "actCancel"],
        "transitions" => [
            "channel" => STATE_CHANNEL,
            "take" => STATE_TAKE,
            "next" => STATE_CHECK_FINISH,
            "pickColor" => STATE_PICK_COLOR,
            "zombiePass" => STATE_CHECK_FINISH,
        ],
    ],

    STATE_PICK_COLOR => [
        "name" => "pickColor",
        "description" => clienttranslate(
            '${actplayer} must choose its new energy color'
        ),
        "descriptionmyturn" => clienttranslate(
            '${you} must choose your new energy color by clicking on meditation token'
        ),
        "type" => "activeplayer",
        "args" => "argPickColor",
        "possibleactions" => ["actColor", "actCancel"],
        "transitions" => [
            "take" => STATE_TAKE,
            "finish" => STATE_CHECK_FINISH,
            "zombiePass" => STATE_CHECK_FINISH,
        ],
    ],

    STATE_CHECK_FINISH => [
        "name" => "checkFinish",
        "type" => "game",
        "action" => "stCheckFinish",
        "updateGameProgression" => true,
        "transitions" => ["finish" => 99, "take" => STATE_TAKE],
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
