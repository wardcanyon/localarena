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

$game_options = array(

    100 => array(
        'name' => totranslate('Mission'),
        'values' => array(
            999 => array( 'name' => totranslate('Campaign'), 'tmdisplay' => totranslate('Continue existing campaign or start a new one') ),
            1 => array('name' => totranslate('Mission').' 1'),
            2 => array('name' => totranslate('Mission').' 2'),
            3 => array('name' => totranslate('Mission').' 3'),
            4 => array('name' => totranslate('Mission').' 4'),
            5 => array('name' => totranslate('Mission').' 5'),
            6 => array('name' => totranslate('Mission').' 6'),
            7 => array('name' => totranslate('Mission').' 7'),
            8 => array('name' => totranslate('Mission').' 8'),
            9 => array('name' => totranslate('Mission').' 9'),
            10 => array('name' => totranslate('Mission').' 10'),
            11 => array('name' => totranslate('Mission').' 11'),
            12 => array('name' => totranslate('Mission').' 12'),
            13 => array('name' => totranslate('Mission').' 13'),
            14 => array('name' => totranslate('Mission').' 14'),
            15 => array('name' => totranslate('Mission').' 15'),
            16 => array('name' => totranslate('Mission').' 16'),
            17 => array('name' => totranslate('Mission').' 17'),
            18 => array('name' => totranslate('Mission').' 18'),
            19 => array('name' => totranslate('Mission').' 19'),
            20 => array('name' => totranslate('Mission').' 20'),
            21 => array('name' => totranslate('Mission').' 21'),
            22 => array('name' => totranslate('Mission').' 22'),
            23 => array('name' => totranslate('Mission').' 23'),
            24 => array('name' => totranslate('Mission').' 24'),
            25 => array('name' => totranslate('Mission').' 25'),
            26 => array('name' => totranslate('Mission').' 26'),
            27 => array('name' => totranslate('Mission').' 27'),
            28 => array('name' => totranslate('Mission').' 28'),
            29 => array('name' => totranslate('Mission').' 29'),
            30 => array('name' => totranslate('Mission').' 30'),
            31 => array('name' => totranslate('Mission').' 31'),
            32 => array('name' => totranslate('Mission').' 32'),
            33 => array('name' => totranslate('Mission').' 33'),
            34 => array('name' => totranslate('Mission').' 34'),
            35 => array('name' => totranslate('Mission').' 35'),
            36 => array('name' => totranslate('Mission').' 36'),
            37 => array('name' => totranslate('Mission').' 37'),
            38 => array('name' => totranslate('Mission').' 38'),
            39 => array('name' => totranslate('Mission').' 39'),
            40 => array('name' => totranslate('Mission').' 40'),
            41 => array('name' => totranslate('Mission').' 41'),
            42 => array('name' => totranslate('Mission').' 42'),
            43 => array('name' => totranslate('Mission').' 43'),
            44 => array('name' => totranslate('Mission').' 44'),
            45 => array('name' => totranslate('Mission').' 45'),
            46 => array('name' => totranslate('Mission').' 46'),
            47 => array('name' => totranslate('Mission').' 47'),
            48 => array('name' => totranslate('Mission').' 48'),
            49 => array('name' => totranslate('Mission').' 49'),
            50 => array('name' => totranslate('Mission').' 50'),            
            )
        ),
    
    101 => array(
        'name' => totranslate('Challenge mode for Three'),
        'values' => array(
            1 => array( 'name' => totranslate('Off')),
            2 => array( 'name' => totranslate('On')),
        ),
        'startcondition' => array(
            2 => array(
                array(
                    'type' => 'maxplayers',
                    'value' => 3,
                    'message' => totranslate('Challenge mode for Three is only for 3 players.'),
                    'gamestartonly' => true
                ),
            )
        )
    ),   
);


