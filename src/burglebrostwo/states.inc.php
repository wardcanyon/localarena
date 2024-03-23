<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

/*
 *
 *   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
 *   in a very easy way from this configuration file.
 *
 *
 *   States types:
 *   _ manager: game manager can make the game progress to the next state.
 *   _ game: this is an (unstable) game state. the game is going to progress to the next state as soon as current action has been accomplished
 *   _ activeplayer: an action is expected from the activeplayer
 *
 *   Arguments:
 *   _ possibleactions: array that specify possible player actions on this step (for state types "manager" and "activeplayer")
 *       (correspond to actions names)
 *   _ action: name of the method to call to process the action (for state type "game")
 *   _ transitions: name of transitions and corresponding next state
 *       (name of transitions correspond to "nextState" argument)
 *   _ description: description is displayed on top of the main content.
 *   _ descriptionmyturn (optional): alternative description displayed when it's player's turn
 *
 */

// These are special actions that can be taken by any player at any
// point.
if (!defined("ACTIONS_ANY_TIME")) {
    define("ACTIONS_ANY_TIME", ["actChangeGameFlowSettings"]);
}

$machinestates = [
    // --------------
    // States required by the BGA framework; do not modify.
    // --------------

    // Initial state.
    ST_GAME_SETUP => [
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => [
            "" => ST_CHARACTER_SELECTION,
        ],
    ],

    // Final state.
    ST_GAME_END => [
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd",
    ],

    // --------------
    // Pre-game setup states
    // --------------

    // 2 => array(
    //     'name' => 'randomizeWalls',
    //     'description' => clienttranslate('The administrator of the table can generate new walls for this game'),
    //     'descriptionmyturn' => clienttranslate('${you} can generate a new set of walls for this game'),
    //     'type' => 'activeplayer',
    //     'action' => 'stRandomizeWalls',
    //     'possibleactions' => array(
    //         'randomizeWalls',
    //     ),
    //     'transitions' => array(
    //         'startGame' => 7,
    //     ),
    // ),

    // In this state, the players take turns choosing characters or
    // passing.  If multi-handed play is disabled, each player is
    // asked for exactly one character.  Players who have passed are
    // skipped.  Once 4 characters are chosen or all players have
    // passed, the game continues.
    ST_CHARACTER_SELECTION => [
        "name" => "stCharacterSelection",
        "description" => clienttranslate(
            "Other players must choose a character."
        ),
        "descriptionmyturn" => clienttranslate(
            '${you} must choose a character.'
        ),
        "type" => "multipleactiveplayer",
        "action" => "stCharacterSelection",
        "args" => "argCharacterSelection",
        "possibleactions" => /*ACTIONS_ANY_TIME + */ [
            "actChangeGameFlowSettings",
            "actPlayCard",
            "actPass",
        ],
        // XXX: what exactly does the transitions array contain here?
        "transitions" => [
            "tRoundContinues" => ST_CHARACTER_SELECTION,
            "tRoundDone" => ST_CHARACTER_SELECTION_ROUND_END,
        ],
    ],

    // We transition to this state from ST_CHARACTER_SELECTION once the
    // round is over (that is, each player has either chosen another
    // character or has passed).
    //
    // We transition to this state early if, mid-round, a player
    // selecting a character means that each seat is full (that is,
    // four characters have been selected).
    //
    // From this state, based on game state and options, we either
    // continue to ST_PLACE_ENTRANCE_TOKENS or we return to
    // ST_CHARACTER_SELECTION for another character-selection round.
    ST_CHARACTER_SELECTION_ROUND_END => [
        "name" => "stCharacterSelectionRoundEnd",
        "type" => "game",
        "action" => "stCharacterSelectionRoundEnd",
        "description" => clienttranslate("..."),
        "transitions" => [
            "tDone" => ST_FINISH_SETUP,
            "tAnotherRound" => ST_CHARACTER_SELECTION,
        ],
    ],

    // XXX: This state will be useful when we allow for manual wall
    // placement and/or for players to re-roll walls.  For the time
    // being, walls are randomly placed as part of ST_FINISH_SETUP.
    //
    // ST_PLACE_WALLS => array(
    //     'name' => 'stPlaceWalls',
    // ),

    ST_FINISH_SETUP => [
        "name" => "stFinishSetup",
        "type" => "game",
        "action" => "stFinishSetup",
        "description" => clienttranslate("Finishing setup..."),
        "transitions" => [
            "tDone" => ST_PLACE_ENTRANCE_TOKENS,
        ],
    ],

    // Place two entrance tokens on any of the four corner tiles of
    // the first floor.
    //
    // N.B.: It's intentional that this comes after ST_FINISH_SETUP,
    // since we want players to be able to see walls, bouncers,
    // etc. when making these decisions.
    ST_PLACE_ENTRANCE_TOKENS => [
        "name" => "stPlaceEntranceTokens",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} places entrance tokens'),
        "descriptionmyturn" => clienttranslate(
            '${you} must place entrance tokens'
        ),
        "args" => "argPlaceEntranceTokens",
        "possibleactions" => ACTIONS_ANY_TIME + ["actSelectTile"],
        "transitions" => [
            "tContinue" => ST_PLACE_ENTRANCE_TOKENS,
            "tNextCharacter" => ST_NEXT_CHARACTER,
        ],
    ],

    // Player-character state; the character enters the map at the
    // location of one of the entrance tokens.
    ST_PLAYER_TURN_ENTER_MAP => [
        "name" => "stPlayerTurnEnterMap",
        "type" => "activeplayer",
        "action" => "stPlayerTurnEnterMap",
        "description" => clienttranslate(
            '${activeCharacterName} (${activePlayerName}) must pick an entrance!'
        ),
        "descriptionmyturn" => clienttranslate(
            '${activeCharacterName} (${you}) must pick an entrance!'
        ),
        "args" => "argPlayerTurnEnterMap",
        "possibleactions" => ACTIONS_ANY_TIME + ["actSelectTile"],
        "transitions" => [
            "tResolveEffect" => ST_RESOLVE_EFFECT,
        ],
    ],

    // --------------
    // Normal game-turn states
    // --------------

    // XXX: state for resolving player character turn effects, such as
    // revealing chips/tiles and "when player enters" effects"

    ST_PLAYER_TURN => [
        "name" => "stPlayerTurn",
        "description" => clienttranslate(
            '${activeCharacterName} (${activePlayerName}) has ${actionsRemaining} actions remaining.'
        ),
        "descriptionmyturn" => clienttranslate(
            '${activeCharacterName} (${you}) has ${actionsRemaining} actions remaining.'
        ),
        "type" => "activeplayer",
        "action" => "stPlayerTurn",
        "args" => "argPlayerTurn",
        "updateGameProgression" => true,
        "possibleactions" => /*ACTIONS_ANY_TIME + */ [
            "actMove",
            "actPeek",
            "actPass",
            "actPrepGear",
            "actPlayCard", // to play gear already prepped
            "actTriggerWhileHere",
        ],
        "transitions" => [
            "tContinue" => ST_PLAYER_TURN,
            "tDone" => ST_PLAYER_TURN_ENDS,
            "tResolveEffects" => ST_RESOLVE_EFFECT,
        ],
    ],

    ST_RESOLVE_EFFECT => [
        "name" => "stResolveEffect",
        "description" => clienttranslate("Resolving game effects..."),
        "type" => "game",
        "action" => "stResolveEffect",
        "transitions" => [
            "tNextEffect" => ST_RESOLVE_EFFECT,
            "tContinuePcTurn" => ST_PLAYER_TURN,
            "tContinueNpcTurn" => ST_NPC_TURN,
            "tActionWindow" => ST_ACTION_WINDOW,
        ],
    ],

    ST_PLAYER_TURN_ENDS => [
        "name" => "stPlayerTurnEnds",
        "description" => clienttranslate("Resolving end-of-turn effects..."),
        "type" => "game",
        "action" => "stPlayerTurnEnds",
        "transitions" => [
            "tNextCharacter" => ST_NEXT_CHARACTER,
        ],
    ],

    // XXX: state for resolving NPC turn effects, such as "when bouncer enters"

    ST_NPC_TURN => [
        "name" => "stNpcTurn",
        "action" => "stNpcTurn",
        "type" => "game",
        "description" => "NPC acting...",
        "possibleactions" => ACTIONS_ANY_TIME + [
            "actPass",
            "actPlayCard", // to play gear already prepped
        ],
        "transitions" => [
            "tStep" => ST_ACTION_WINDOW,
            "tDone" => ST_NEXT_CHARACTER,
            "tResolveEffect" => ST_RESOLVE_EFFECT,
        ],
    ],

    // XXX: revise comment
    //
    // We transition into this state at points in the NPC turn when
    // player characters can act and when at least one player has NPC
    // stepping enabled.  Each player can act and/or pass to let the
    // NPC turn continue.
    ST_ACTION_WINDOW => [
        "name" => "stActionWindow",
        "action" => "stActionWindow",
        "args" => "argActionWindow",
        "type" => "multipleactiveplayer",
        "description" => "Action window!",
        "possibleactions" => ACTIONS_ANY_TIME + [
            "actPass",
            "actPlayCard", // to play gear already prepped
        ],
        "transitions" => [
            "tStep" => ST_ACTION_WINDOW,
            // 'tContinuePcTurn' => ST_PLAYER_TURN,
            // 'tContinueNpcTurn' => ST_NPC_TURN,
            "tContinue" => ST_RESOLVE_EFFECT,
        ],
    ],

    // XXX: Do we want a distinct "CHARACTER_TURN_ENDS" state (or
    // maybe two, one for players and one for NPCs)?

    ST_NEXT_CHARACTER => [
        "name" => "stNextCharacter",
        "description" => "...",
        "type" => "game",
        "action" => "stNextCharacter",
        "transitions" => [
            "tPlayerCharacterTurn" => ST_PLAYER_TURN,
            "tPlayerCharacterTurnEnterMap" => ST_PLAYER_TURN_ENTER_MAP,
            "tNpcTurn" => ST_NPC_TURN,
        ],
    ],

    // // XXX: BUG:
    // // This state asks players to choose an even-numbered tile for
    // // the bouncer to jump to when a Distracted patrol card is
    // // drawn.
    // //
    // // XXX: This might not be the right way to model this---we might need
    // // to do this at any point when the bouncer moves, and that can happen
    // // when a commotion is made or anything else.
    //
    // ST_PLAYERCHOICE_PATROL_DISTRACTED => array(
    // ),

    // // XXX: what sort of effects are these?
    // 11 => array(
    //     'name' => 'endTurn',
    //     'description' => clienttranslate('Triggering end of turn effects...'),
    //     'type' => 'game',
    //     'args' => 'argPlayerTurn',
    //     'action' => 'stEndTurn',
    //     'updateGameProgression' => true,
    //     'transitions' => array(
    //         // 'moveBouncer' => 12,
    //         // 'chooseAlarm' => 20,
    //     ),
    // ),

    // 12 => array(
    //     'name' => 'moveBouncer',
    //     'description' => clienttranslate('Guard is moving...'),
    //     'type' => 'game',
    //     'action' => 'stMoveGuard',
    //     'updateGameProgression' => true,
    //     'transitions' => array(
    //         'nextPlayer' => 12,
    //         'chooseAlarm' => 20,
    //         'gameOver' => 90,
    //     ),
    // ),

    // 13 => array(
    //     'name' => 'cardChoice',
    //     'description' => clienttranslate('${card_name_displayed}: ${actplayer} must choose ${choice_description}'),
    //     'descriptionmyturn' => clienttranslate('${card_name_displayed}: ${you} must choose ${choice_description}'),
    //     'type' => 'activeplayer',
    //     'args' => 'argCardChoice',
    //     'updateGameProgression' => true,
    //     'possibleactions' => array( 'selectCardChoice', 'cancelCardChoice', 'restartTurn' ),
    //     'transitions' => array( 'endAction' => 21, 'nextAction' => 9, 'endTurn' => 10, 'tileChoice' => 14, 'restartTurn' => 9, 'chooseAlarm' => 20, 'gameOver' => 90 )
    // ),

    // 14 => array(
    //     'name' => 'tileChoice',
    //     'description' => clienttranslate('${tile_name}: ${actplayer} must choose an option'),
    //     'descriptionmyturn' => clienttranslate('${tile_name}: ${you} must choose an option'),
    //     'type' => 'activeplayer',
    //     'args' => 'argTileChoice',
    //     'possibleactions' => array( 'selectTileChoice', 'restartTurn' ),
    //     'transitions' => array( 'endAction' => 21, 'tileChoice' => 14, 'restartTurn' => 9, 'endTurn' => 10, 'switchRookMove' => 25, 'chooseAlarm' => 20 )
    // ),

    // 15 => array(
    //     'name' => 'playerChoice',
    //     'description' => clienttranslate('${actplayer} must choose a player'),
    //     'descriptionmyturn' => clienttranslate('${you} must choose a player'),
    //     'type' => 'activeplayer',
    //     'args' => 'argPlayerChoice',
    //     'possibleactions' => array( 'selectPlayerChoice', 'cancelPlayerChoice', 'restartTurn' ),
    //     'transitions' => array( 'endAction' => 21, 'nextAction' => 9, 'proposeTrade' => 16, 'specialChoice' => 20, 'chooseAlarm' => 20, 'restartTurn' => 9 )
    // ),

    // 16 => array(
    //     'name' => 'proposeTrade',
    //     'description' => clienttranslate('${actplayer} must choose cards to trade'),
    //     'descriptionmyturn' => clienttranslate('${you} must choose cards to trade'),
    //     'type' => 'activeplayer',
    //     'args' => 'argProposeTrade',
    //     'possibleactions' => array( 'proposeTrade', 'cancelTrade' ),
    //     'transitions' => array( 'endAction' => 21, 'nextAction' => 9, 'nextTradePlayer' => 18, 'endTradeOtherPlayer' => 19 )
    // ),

    // 17 => array(
    //     'name' => 'confirmTrade',
    //     'description' => clienttranslate('${actplayer} must confirm a trade'),
    //     'descriptionmyturn' => clienttranslate('${you} must confirm a trade'),
    //     'type' => 'activeplayer',
    //     'args' => 'argConfirmTrade',
    //     'possibleactions' => array( 'confirmTrade', 'cancelTrade' ),
    //     'transitions' => array( 'endTradeOtherPlayer' => 19 )
    // ),

    // 18 => array(
    //     'name' => 'nextTradePlayer',
    //     'description' => '',
    //     'type' => 'game',
    //     'action' => 'stNextTradePlayer',
    //     'transitions' => array( 'confirmTrade' => 17 )
    // ),

    // 19 => array(
    //     'name' => 'endTradeOtherPlayer',
    //     'description' => '',
    //     'type' => 'game',
    //     'action' => 'stEndTradeOtherPlayer',
    //     'transitions' => array( 'nextAction' => 9 )
    // ),

    // 20 => array(
    //     'name' => 'specialChoice',
    //     'description' => clienttranslate('${choice_name}: ${actplayer} must choose ${choice_description}'),
    //     'descriptionmyturn' => clienttranslate('${choice_name}: ${you} must choose ${choice_description}'),
    //     'type' => 'activeplayer',
    //     'args' => 'argSpecialChoice',
    //     'updateGameProgression' => true,
    //     'possibleactions' => array( 'selectSpecialChoice', 'cancelSpecialChoice' ),
    //     'transitions' => array( 'endAction' => 21, 'nextAction' => 9, 'tileChoice' => 14, 'playerTurn' => 9, 'moveGuard' => 11, 'chooseAlarm' => 20, 'switchRookMove' => 25, 'gameOver' => 90 )
    // ),

    // 21 => array(
    //     'name' => 'endAction',
    //     'description' => '',
    //     'type' => 'game',
    //     'action' => 'stEndAction',
    //     'transitions' => array( 'nextAction' => 9, 'drawTools' => 22, 'endTurn' => 10 )
    // ),

    // 22 => array(
    //     'name' => 'drawToolsAndDiscard',
    //     'description' => clienttranslate('${actplayer} must choose a tool'),
    //     'descriptionmyturn' => clienttranslate('${you} must choose a tool'),
    //     'type' => 'activeplayer',
    //     'args' => 'argDrawToolsAndDiscard',
    //     'possibleactions' => array( 'keepTool', 'restartTurn' ),
    //     'transitions' => array( 'drawToolsOtherPlayer' => 23, 'nextAction' => 9, 'endTurn' => 10, 'restartTurn' => 9 )
    // ),

    // 23 => array(
    //     'name' => 'drawToolsOtherPlayer',
    //     'description' => '',
    //     'type' => 'game',
    //     'action' => 'stDrawToolsOtherPlayer',
    //     'transitions' => array( 'nextAction' => 9 )
    // ),

    // 24 => array(
    //     'name' => 'takeCards',
    //     'description' => clienttranslate('${actplayer} must choose cards to take'),
    //     'descriptionmyturn' => clienttranslate('${you} must choose cards to take'),
    //     'type' => 'activeplayer',
    //     'args' => 'argPlayerTurn',
    //     'possibleactions' => array( 'confirmTakeCards', 'cancelTakeCards' ),
    //     'transitions' => array( 'endAction' => 21, 'nextAction' => 9 )
    // ),

    // 25 => array(
    //     'name' => 'switchRookMove',
    //     'description' => '',
    //     'type' => 'game',
    //     'action' => 'stSwitchRookMove',
    //     'transitions' => array( 'confirmRookMove' => 26, 'endAction' => 21, 'switchRookMove' => 25, 'tileChoice' => 14 )
    // ),

    // 26 => array(
    //     'name' => 'confirmRookMove',
    //     'description' => clienttranslate('${actplayer} must confirm The Rook move'),
    //     'descriptionmyturn' => clienttranslate('The Rook wants to move you to ${destination_name} on floor ${floor}'),
    //     'type' => 'activeplayer',
    //     'args' => 'argConfirmRookMove',
    //     'possibleactions' => array( 'confirmRookMove', 'cancelRookMove' ),
    //     'transitions' => array( 'switchRookMove' => 25, 'gameOver' => 90, 'tileChoice' => 14, 'chooseAlarm' => 20 )
    // ),

    90 => [
        "name" => "gameOver",
        "description" => clienttranslate("End of game"),
        "type" => "game",
        "action" => "stGameOver",
        "transitions" => ["endGame" => 99],
    ],
];
