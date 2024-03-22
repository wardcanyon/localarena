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

if ( !defined('STATE_PREPARATION') )
{
    define("STATE_PREPARATION",2);
    define("STATE_PLAYERTURN",3);
    define("STATE_NEWTRICK",4);
    define("STATE_PICKTASK",5);
    define("STATE_CHECK_PICKTASK",6);
    define("STATE_NEXTPLAYER",7);
    define("STATE_ENDMISSION",8);
    define("STATE_COMM",9);
    define("STATE_BEFORECOMM",10);
    define("STATE_AFTERCOMM",11);
    define("STATE_COMM_TOKEN",12);
    define("STATE_CHANGE_MISSION",13);
    define("STATE_DISTRESS_SETUP",14);
    define("STATE_DISTRESS",15);
    define("STATE_DISTRESS_EXCHANGE",16);
    define("STATE_QUESTION",17);
    define("STATE_NEXTQUESTION",18);
    define("STATE_PICKCREW",19);
    define("STATE_MULTISELECT",20);
    define("STATE_SAVE",21);    
}

$machinestates = array(
    
    // The initial state. Please do not modify.
    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => array( "" => 2 )
    ),
    
    // Note: ID=2 => your first state
    STATE_PREPARATION => array(
        "name" => "preparation",
        "description" => "",
        "type" => "game",
        "action" => "stPreparation",
        "transitions" => array("task" => STATE_PICKTASK, "trick" => STATE_NEWTRICK, "question" => STATE_QUESTION, "pickCrew" => STATE_PICKCREW, "multiSelect" => STATE_MULTISELECT)
    ),    
    
    STATE_QUESTION => array(
        "name" => "question",
        "description" => clienttranslate('Commander ${commander} asks ${actplayer} : ${question}'),
        "descriptionmyturn" => clienttranslate('Commander ${commander} asks ${you} : ${question}'),
        "type" => "activeplayer",
        "args" => "argQuestion",
        "possibleactions" => array("actButton"),
        "transitions" => array("next" => STATE_NEXTQUESTION, "zombiePass" => STATE_CHANGE_MISSION)
    ),
    
    STATE_NEXTQUESTION => array(
        "name" => "nextQuestion",
        "description" => "",
        "type" => "game",
        "action" => "stNextQuestion",
        "transitions" => array("next" => STATE_QUESTION, "pick" => STATE_PICKCREW),
    ),
    
    STATE_PICKCREW => array(
        "name" => "pickCrew",
        "description" => clienttranslate('${actplayer} must choose a crew member'),
        "descriptionmyturn" => clienttranslate('${you} must choose a crew member'),
        "type" => "activeplayer",
        "args" => "argPickCrew",
        "possibleactions" => array("actPickCrew"),
        "transitions" => array("task" => STATE_PICKTASK, "trick" => STATE_NEWTRICK, "next"=>STATE_NEXTQUESTION, "pickCrew" => STATE_PICKCREW, "zombiePass" => STATE_CHANGE_MISSION)
    ),    
    
    STATE_MULTISELECT => array(
        "name" => "multiSelect",
        "description" => clienttranslate('${actplayer} must do according to your mission'),
        "descriptionmyturn" => clienttranslate('${you} must do according to your mission'),
        "type" => "activeplayer",
        "args" => "argMultiSelect",
        "possibleactions" => array("actMultiSelect", "actCancel"),
        "transitions" => array("same" => STATE_MULTISELECT, "cancel" => STATE_PICKTASK,  "task" => STATE_PICKTASK, "zombiePass" => STATE_CHANGE_MISSION)
    ),
    
    STATE_PICKTASK => array(
        "name" => "pickTask",
        "description" => clienttranslate('${actplayer} must choose a task'),
        "descriptionmyturn" => clienttranslate('${you} must choose a task'),
        "type" => "activeplayer",
        "args" => "argPickTask",
        "possibleactions" => array("actChooseTask"),
        "transitions" => array("next" => STATE_CHECK_PICKTASK, "zombiePass" => STATE_CHANGE_MISSION)
    ),
    
    STATE_CHECK_PICKTASK => array(
        "name" => "checkPickTask",
        "description" => "",
        "type" => "game",
        "action" => "stcheckPickTask",
        "transitions" => array("task" => STATE_PICKTASK, "turn" => STATE_NEWTRICK)
    ),
    
    STATE_NEWTRICK => array(
        "name" => "newTrick",
        "description" => "",
        "type" => "game",
        "action" => "stNewTrick",
        "transitions" => array("next" => STATE_PLAYERTURN, "distress" => STATE_DISTRESS_SETUP)
    ),
    
    STATE_DISTRESS_SETUP => array(
        "name" => "distressSetup",
        "description" => clienttranslate('Distress signal : ${actplayer} must decide where to pass the cards'),
        "descriptionmyturn" => clienttranslate('Distress signal : ${you} must decide where to pass the cards'),
        "type" => "activeplayer",
        "args" => "argPlayerTurn",
        "possibleactions" => array("actButton"),
        "transitions" => array("next" => STATE_DISTRESS, "zombiePass" => STATE_CHANGE_MISSION)
    ),
    
    STATE_DISTRESS => array(
        "name" => "distress",
        "args" => "argDistress",
        "type" => "multipleactiveplayer",
        "possibleactions"       => array("actPlayCard"),
        "description" => clienttranslate('Every players must choose a card to pass'),
        "descriptionmyturn" => clienttranslate('${you} must choose a card to pass'),
        "transitions" => array("next" => STATE_DISTRESS_EXCHANGE, "zombiePass" => STATE_CHANGE_MISSION)
    ),
    
    STATE_DISTRESS_EXCHANGE => array(
        "name" => "distressExchange",
        "description" => "",
        "type" => "game",
        "action" => "stDistressExchange",
        "transitions" => array("next" => STATE_PLAYERTURN),
    ),
    
    STATE_PLAYERTURN => array(
        "name" => "playerTurn",
        "description" => clienttranslate('${actplayer} must play a card'),
        "descriptionmyturn" => clienttranslate('${you} must play a card'),
        "type" => "activeplayer",
        "args" => "argPlayerTurn",
        "possibleactions" => array("actPlayCard", "actStartComm", "actDistress"),
        "transitions" => array("next" => STATE_NEXTPLAYER, "startComm" => STATE_BEFORECOMM, "distress" => STATE_DISTRESS_SETUP, "zombiePass" => STATE_CHANGE_MISSION)
    ),
    
    STATE_NEXTPLAYER => array(
        "name" => "nextPlayer",
        "description" => "",
        "type" => "game",
        "action" => "stNextPlayer",
        "transitions" => array("nextPlayer" => STATE_PLAYERTURN,  "nextTrick" => STATE_NEWTRICK, "endMission" => STATE_ENDMISSION),
        "updateGameProgression" => true
    ),
    
    STATE_BEFORECOMM => array(
        "name" => "beforeComm",
        "description" => "",
        "type" => "game",
        "action" => "stBeforeComm",
        "transitions" => array("next" => STATE_COMM)
    ),
    
    STATE_COMM => array(
        "name" => "comm",
        "description" => clienttranslate('${actplayer} must choose a card to communicate'),
        "descriptionmyturn" => clienttranslate('${you} must choose a card to communicate'),
        "type" => "activeplayer",
        "args" => "argComm",
        "possibleactions" => array("actPlayCard", "actCancel"),
        "transitions" => array("next" => STATE_COMM_TOKEN, "cancel" => STATE_AFTERCOMM, "after" => STATE_AFTERCOMM, "zombiePass" => STATE_CHANGE_MISSION)
    ),
    
    STATE_COMM_TOKEN => array(
        "name" => "commToken",
        "description" => clienttranslate('${actplayer} must place its communication token'),
        "descriptionmyturn" => clienttranslate('${you} must place your communication token'),
        "type" => "activeplayer",
        "args" => "argCommToken",
        "possibleactions" => array("actFinishComm"),
        "transitions" => array("next" => STATE_AFTERCOMM, "zombiePass" => STATE_CHANGE_MISSION)
    ),
    
    STATE_AFTERCOMM => array(
        "name" => "afterComm",
        "description" => "",
        "type" => "game",
        "action" => "stAfterComm",
        "transitions" => array("next" => STATE_PLAYERTURN)
    ),
        
    STATE_ENDMISSION => array(
        "name" => "endMission",
        "args" => "argEndMission",
        "type" => "multipleactiveplayer",
        "possibleactions"       => array("actButton"),
        "description" => clienttranslate('Every players must continue or stop'),
        "descriptionmyturn" => clienttranslate('${you} must continue or stop'),
        "transitions" => array("next" => STATE_CHANGE_MISSION, "end" => STATE_SAVE, "zombiePass" => STATE_CHANGE_MISSION)
    ),
    
    STATE_CHANGE_MISSION => array(
        "name" => "changeMission",
        "description" => "",
        "type" => "game",
        "action" => "stChangeMission",
        "transitions" => array("next" => STATE_PREPARATION, "save" => STATE_SAVE, "end" => STATE_SAVE)
    ),
    
    STATE_SAVE => array(
        "name" => "save",
        "description" => "",
        "type" => "game",
        "action" => "stSave",
        "transitions" => array("next" => 99)
    ),    
    
    // Final state.
    // Please do not modify (and do not overload action/args methods).
    99 => array(
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    )
    
);



