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

$stats_type = array(

    // Statistics global to table
    "table" => array(

    ),
    
    // Statistics existing for each player
    "player" => array(
        
        "turns_number" => array("id"=> 10,
            "name" => totranslate("Number of turns"),
            "type" => "int" ),
        
        "chakra_harmonized" => array("id"=> 11,
            "name" => totranslate("Number of harmonized chakra"),
            "type" => "int" ),
        
        "chakra_aligned" => array("id"=> 12,
            "name" => totranslate("Number of aligned harmonized chakra "),
            "type" => "int" ),
        
        "chakra_points" => array("id"=> 13,
            "name" => totranslate("Number of points gains with chakra"),
            "type" => "int" ),
        
        "black_points" => array("id"=> 14,
            "name" => totranslate("Number of points gains with alleviated black energy"),
            "type" => "int" ),
        
        "harmo_points" => array("id"=> 15,
            "name" => totranslate("Number of points gains with harmonization bonus"),
            "type" => "int" ),
        
        "meditation" => array("id"=> 16,
            "name" => totranslate("Number of meditation done"),
            "type" => "int" ),
   
    )

);
