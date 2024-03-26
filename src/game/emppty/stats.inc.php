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
 * stats.inc.php
 *
 * Chakra game statistics description
 *
 */

$stats_type = [
    // Statistics global to table
    "table" => [],

    // Statistics existing for each player
    "player" => [
        "turns_number" => [
            "id" => 10,
            "name" => totranslate("Number of turns"),
            "type" => "int",
        ],

        "chakra_harmonized" => [
            "id" => 11,
            "name" => totranslate("Number of harmonized chakra"),
            "type" => "int",
        ],

        "chakra_aligned" => [
            "id" => 12,
            "name" => totranslate("Number of aligned harmonized chakra "),
            "type" => "int",
        ],

        "chakra_points" => [
            "id" => 13,
            "name" => totranslate("Number of points gains with chakra"),
            "type" => "int",
        ],

        "black_points" => [
            "id" => 14,
            "name" => totranslate(
                "Number of points gains with alleviated black energy"
            ),
            "type" => "int",
        ],

        "harmo_points" => [
            "id" => 15,
            "name" => totranslate(
                "Number of points gains with harmonization bonus"
            ),
            "type" => "int",
        ],

        "meditation" => [
            "id" => 16,
            "name" => totranslate("Number of meditation done"),
            "type" => "int",
        ],
    ],
];
