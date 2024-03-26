<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * thecrew implementation : © Nicolas Gocel <nicolas.gocel@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * states.inc.php
 *
 * thecrew game states description
 *
 */

//    !! It is not a good idea to modify this file when a game is running !!

if (!defined("STATE_PREPARATION")) {
    define("STATE_PREPARATION", 2);
    define("STATE_PLAYERTURN", 3);
    define("STATE_NEWTRICK", 4);
    define("STATE_PICKTASK", 5);
    define("STATE_CHECK_PICKTASK", 6);
    define("STATE_NEXTPLAYER", 7);
    define("STATE_ENDMISSION", 8);
    define("STATE_COMM", 9);
    define("STATE_BEFORECOMM", 10);
    define("STATE_AFTERCOMM", 11);
    define("STATE_COMM_TOKEN", 12);
    define("STATE_CHANGE_MISSION", 13);
    define("STATE_DISTRESS_SETUP", 14);
    define("STATE_DISTRESS", 15);
    define("STATE_DISTRESS_EXCHANGE", 16);
    define("STATE_QUESTION", 17);
    define("STATE_NEXTQUESTION", 18);
    define("STATE_PICKCREW", 19);
    define("STATE_MULTISELECT", 20);
    define("STATE_SAVE", 21);
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

    // Note: ID=2 => your first state
    STATE_PREPARATION => [
        "name" => "preparation",
        "description" => "",
        "type" => "game",
        "action" => "stPreparation",
        "transitions" => [
            "task" => STATE_PICKTASK,
            "trick" => STATE_NEWTRICK,
            "question" => STATE_QUESTION,
            "pickCrew" => STATE_PICKCREW,
            "multiSelect" => STATE_MULTISELECT,
        ],
    ],

    STATE_QUESTION => [
        "name" => "question",
        "description" => clienttranslate(
            'Commander ${commander} asks ${actplayer} : ${question}'
        ),
        "descriptionmyturn" => clienttranslate(
            'Commander ${commander} asks ${you} : ${question}'
        ),
        "type" => "activeplayer",
        "args" => "argQuestion",
        "possibleactions" => ["actButton"],
        "transitions" => [
            "next" => STATE_NEXTQUESTION,
            "zombiePass" => STATE_CHANGE_MISSION,
        ],
    ],

    STATE_NEXTQUESTION => [
        "name" => "nextQuestion",
        "description" => "",
        "type" => "game",
        "action" => "stNextQuestion",
        "transitions" => ["next" => STATE_QUESTION, "pick" => STATE_PICKCREW],
    ],

    STATE_PICKCREW => [
        "name" => "pickCrew",
        "description" => clienttranslate(
            '${actplayer} must choose a crew member'
        ),
        "descriptionmyturn" => clienttranslate(
            '${you} must choose a crew member'
        ),
        "type" => "activeplayer",
        "args" => "argPickCrew",
        "possibleactions" => ["actPickCrew"],
        "transitions" => [
            "task" => STATE_PICKTASK,
            "trick" => STATE_NEWTRICK,
            "next" => STATE_NEXTQUESTION,
            "pickCrew" => STATE_PICKCREW,
            "zombiePass" => STATE_CHANGE_MISSION,
        ],
    ],

    STATE_MULTISELECT => [
        "name" => "multiSelect",
        "description" => clienttranslate(
            '${actplayer} must do according to your mission'
        ),
        "descriptionmyturn" => clienttranslate(
            '${you} must do according to your mission'
        ),
        "type" => "activeplayer",
        "args" => "argMultiSelect",
        "possibleactions" => ["actMultiSelect", "actCancel"],
        "transitions" => [
            "same" => STATE_MULTISELECT,
            "cancel" => STATE_PICKTASK,
            "task" => STATE_PICKTASK,
            "zombiePass" => STATE_CHANGE_MISSION,
        ],
    ],

    STATE_PICKTASK => [
        "name" => "pickTask",
        "description" => clienttranslate('${actplayer} must choose a task'),
        "descriptionmyturn" => clienttranslate('${you} must choose a task'),
        "type" => "activeplayer",
        "args" => "argPickTask",
        "possibleactions" => ["actChooseTask"],
        "transitions" => [
            "next" => STATE_CHECK_PICKTASK,
            "zombiePass" => STATE_CHANGE_MISSION,
        ],
    ],

    STATE_CHECK_PICKTASK => [
        "name" => "checkPickTask",
        "description" => "",
        "type" => "game",
        "action" => "stcheckPickTask",
        "transitions" => ["task" => STATE_PICKTASK, "turn" => STATE_NEWTRICK],
    ],

    STATE_NEWTRICK => [
        "name" => "newTrick",
        "description" => "",
        "type" => "game",
        "action" => "stNewTrick",
        "transitions" => [
            "next" => STATE_PLAYERTURN,
            "distress" => STATE_DISTRESS_SETUP,
        ],
    ],

    STATE_DISTRESS_SETUP => [
        "name" => "distressSetup",
        "description" => clienttranslate(
            'Distress signal : ${actplayer} must decide where to pass the cards'
        ),
        "descriptionmyturn" => clienttranslate(
            'Distress signal : ${you} must decide where to pass the cards'
        ),
        "type" => "activeplayer",
        "args" => "argPlayerTurn",
        "possibleactions" => ["actButton"],
        "transitions" => [
            "next" => STATE_DISTRESS,
            "zombiePass" => STATE_CHANGE_MISSION,
        ],
    ],

    STATE_DISTRESS => [
        "name" => "distress",
        "args" => "argDistress",
        "type" => "multipleactiveplayer",
        "possibleactions" => ["actPlayCard"],
        "description" => clienttranslate(
            "Every players must choose a card to pass"
        ),
        "descriptionmyturn" => clienttranslate(
            '${you} must choose a card to pass'
        ),
        "transitions" => [
            "next" => STATE_DISTRESS_EXCHANGE,
            "zombiePass" => STATE_CHANGE_MISSION,
        ],
    ],

    STATE_DISTRESS_EXCHANGE => [
        "name" => "distressExchange",
        "description" => "",
        "type" => "game",
        "action" => "stDistressExchange",
        "transitions" => ["next" => STATE_PLAYERTURN],
    ],

    STATE_PLAYERTURN => [
        "name" => "playerTurn",
        "description" => clienttranslate('${actplayer} must play a card'),
        "descriptionmyturn" => clienttranslate('${you} must play a card'),
        "type" => "activeplayer",
        "args" => "argPlayerTurn",
        "possibleactions" => ["actPlayCard", "actStartComm", "actDistress"],
        "transitions" => [
            "next" => STATE_NEXTPLAYER,
            "startComm" => STATE_BEFORECOMM,
            "distress" => STATE_DISTRESS_SETUP,
            "zombiePass" => STATE_CHANGE_MISSION,
        ],
    ],

    STATE_NEXTPLAYER => [
        "name" => "nextPlayer",
        "description" => "",
        "type" => "game",
        "action" => "stNextPlayer",
        "transitions" => [
            "nextPlayer" => STATE_PLAYERTURN,
            "nextTrick" => STATE_NEWTRICK,
            "endMission" => STATE_ENDMISSION,
        ],
        "updateGameProgression" => true,
    ],

    STATE_BEFORECOMM => [
        "name" => "beforeComm",
        "description" => "",
        "type" => "game",
        "action" => "stBeforeComm",
        "transitions" => ["next" => STATE_COMM],
    ],

    STATE_COMM => [
        "name" => "comm",
        "description" => clienttranslate(
            '${actplayer} must choose a card to communicate'
        ),
        "descriptionmyturn" => clienttranslate(
            '${you} must choose a card to communicate'
        ),
        "type" => "activeplayer",
        "args" => "argComm",
        "possibleactions" => ["actPlayCard", "actCancel"],
        "transitions" => [
            "next" => STATE_COMM_TOKEN,
            "cancel" => STATE_AFTERCOMM,
            "after" => STATE_AFTERCOMM,
            "zombiePass" => STATE_CHANGE_MISSION,
        ],
    ],

    STATE_COMM_TOKEN => [
        "name" => "commToken",
        "description" => clienttranslate(
            '${actplayer} must place its communication token'
        ),
        "descriptionmyturn" => clienttranslate(
            '${you} must place your communication token'
        ),
        "type" => "activeplayer",
        "args" => "argCommToken",
        "possibleactions" => ["actFinishComm"],
        "transitions" => [
            "next" => STATE_AFTERCOMM,
            "zombiePass" => STATE_CHANGE_MISSION,
        ],
    ],

    STATE_AFTERCOMM => [
        "name" => "afterComm",
        "description" => "",
        "type" => "game",
        "action" => "stAfterComm",
        "transitions" => ["next" => STATE_PLAYERTURN],
    ],

    STATE_ENDMISSION => [
        "name" => "endMission",
        "args" => "argEndMission",
        "type" => "multipleactiveplayer",
        "possibleactions" => ["actButton"],
        "description" => clienttranslate("Every players must continue or stop"),
        "descriptionmyturn" => clienttranslate('${you} must continue or stop'),
        "transitions" => [
            "next" => STATE_CHANGE_MISSION,
            "end" => STATE_SAVE,
            "zombiePass" => STATE_CHANGE_MISSION,
        ],
    ],

    STATE_CHANGE_MISSION => [
        "name" => "changeMission",
        "description" => "",
        "type" => "game",
        "action" => "stChangeMission",
        "transitions" => [
            "next" => STATE_PREPARATION,
            "save" => STATE_SAVE,
            "end" => STATE_SAVE,
        ],
    ],

    STATE_SAVE => [
        "name" => "save",
        "description" => "",
        "type" => "game",
        "action" => "stSave",
        "transitions" => ["next" => 99],
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
