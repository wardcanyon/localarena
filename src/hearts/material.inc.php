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
 * material.inc.php
 *
 * Hearts game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */

/**
 * Note: this code is modified to add suggestions from BGA players and popular variants.
 * Please visit here to read the basic code used in the BGA Studio tutorial: https://github.com/elaskavaia/
 */

// Suit and card data, added additional classes (suit_N) for custom CSS
$this->colors = [
    1 => ['name' => '<span style="color:black" class="suit_1">♠</span>'],
    2 => ['name' => '<span style="color:red" class="suit_2">♥</span>'],
    3 => ['name' => '<span style="color:black" class="suit_3">♣</span>'],
    4 => ['name' => '<span style="color:red" class="suit_4">♦</span>'],
];

$this->values_label = [
    2 => '2',
    3 => '3',
    4 => '4',
    5 => '5',
    6 => '6',
    7 => '7',
    8 => '8',
    9 => '9',
    10 => '10',
    11 => 'J',
    12 => 'Q',
    13 => 'K',
    14 => 'A'
];

// Audio file list
$this->audio_list = ['break', 'give', 'jack', 'play', 'queen', 'shuffle', 'take'];