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
 * material.inc.php
 *
 * Chakra game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */

$this->colors = [
    1 => "purple",
    2 => "darkblue",
    3 => "blue",
    4 => "green",
    5 => "yellow",
    6 => "orange",
    7 => "red",
    8 => "black",
];

$this->colorstr = [
    "purple" => clienttranslate("purple"),
    "darkblue" => clienttranslate("dark blue"),
    "blue" => clienttranslate("blue"),
    "green" => clienttranslate("green"),
    "yellow" => clienttranslate("yellow"),
    "orange" => clienttranslate("orange"),
    "red" => clienttranslate("red"),
    "black" => clienttranslate("black"),
];

$this->channels = [
    1 => [[3]],
    2 => [[1, 1, 1]],
    3 => [[2, 1], [1, 2]],
    4 => [[-2]],
    5 => [[-1, -1]],
    6 => [[-1, 1], [1, -1]],
    7 => [[1], [-1]],
    8 => [],
];
