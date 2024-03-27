<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Hearts implementation fixes: © ufm <tel2tale@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * stats.inc.php
 *
 * Hearts game statistics description
 *
 */

/*
    In this file, you are describing game statistics, that will be displayed at the end of the
    game.
    
    There are 2 types of statistics:
    _ table statistics, that are not associated to a specific player (ie: 1 value for each game).
    _ player statistics, that are associated to each players (ie: 1 value for each player in the game).

    Statistics types can be "int" for integer, and "float" for floating point values.
    
    Once you defined your statistics there, you can start using "initStat", "setStat" and "incStat" method
    in your game logic, using statistics names defined below.
*/

//   !! It is not a good idea to modify this file when a game is running !!

/**
 * Note: this code is modified to add suggestions from BGA players and popular variants.
 * Please visit here to read the basic code used in the BGA Studio tutorial: https://github.com/elaskavaia/
 */

$stats_type = [
    // Statistics global to table
    "table" => [
        "handNbr" => [
            "id" => 10,
            "name" => totranslate("Number of hands"),
            "type" => "int",
        ],
    ],
    
    // Statistics existing for each player
    "player" => [
        "getQueenOfSpades" => [
            "id" => 10,
            "name" => totranslate("Captured Queens of Spades"),
            "type" => "int",
        ],
        "getHeart" => [
            "id" => 11,
            "name" => totranslate("Captured Heart cards"),
            "type" => "int",
        ],
        "getAllPointCards" => [
            "id" => 12,
            "name" => totranslate("Shot the moon"),
            "type" => "int",
        ],
        "getNoPoints" => [
            "id" => 13,
            "name" => totranslate("Hands with no penalty points"),
            "type" => "int",
        ],
        "getJackOfDiamonds" => [
            "id" => 14,
            "name" => totranslate("Captured Jacks of Diamonds"),
            "type" => "int",
        ],
        "getKingOfSpades" => [
            "id" => 15,
            "name" => totranslate("Captured Kings of Spades"),
            "type" => "int",
        ],
        "getAceOfSpades" => [
            "id" => 16,
            "name" => totranslate("Captured Aces of Spades"),
            "type" => "int",
        ],
    ],
];