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
 * material.inc.php
 *
 * EmptyGame game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *   
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */

$this->colors = array(
    
    1 => array( 'name' => clienttranslate('Blue'),
        'nameof' => clienttranslate('Blues'),
        'nametr' => self::_('Blue'),
        'color' => 'blue'
        ),
    
    2 => array( 'name' => clienttranslate('Green'),
        'nameof' => clienttranslate('Greens'),
        'nametr' => self::_('Green'),
        'color' => 'green'
        ),
    
    3 => array( 'name' => clienttranslate('Pink'),
        'nameof' => clienttranslate('Pinks'),
        'nametr' => self::_('Pink'),
        'color' => 'pink'
    ),
    
    4 => array( 'name' => clienttranslate('Yellow'),
        'nameof' => clienttranslate('Yellows'),
        'nametr' => self::_('Yellow'),
        'color' => 'yellow'
        ),
    
    5 => array( 'name' => clienttranslate('Rocket'),
        'nameof' => clienttranslate('Rockets'),
        'nametr' => self::_('Rocket'),
        'color' => 'black'
    ),
    
    6 => array( 'name' => clienttranslate('Communication'),
        'nameof' => clienttranslate('Communication'),
        'nametr' => self::_('Communication'),
        'color' => 'black'
    )
);

$this->missions = array(
    1 => array ( 'id' => 1, 'tasks' => 1, 'tiles' => ''),
    2 => array ( 'id' => 2, 'tasks' => 2, 'tiles' => ''),
    3 => array ( 'id' => 3, 'tasks' => 2, 'tiles' => '1,2'),
    4 => array ( 'id' => 4, 'tasks' => 3, 'tiles' => ''),
    5 => array ( 'id' => 5, 'tasks' => 0, 'tiles' => '', 'question' => clienttranslate('How do you feel?'), 'replies' => clienttranslate('Good/Bad')),
    6 => array ( 'id' => 6, 'tasks' => 3, 'tiles' => 'i1,i2', 'deadzone' => true),
    7 => array ( 'id' => 7, 'tasks' => 3, 'tiles' => 'o'),
    8 => array ( 'id' => 8, 'tasks' => 3, 'tiles' => '1,2,3'),
    9 => array ( 'id' => 9, 'tasks' => 0, 'tiles' => ''),
    10 => array ( 'id' => 10, 'tasks' => 4, 'tiles' => ''),
    11 => array ('id' => 11, 'tasks' => 4, 'tiles' => '1'),
    12 => array ('id' => 12, 'tasks' => 4, 'tiles' => 'o'),
    13 => array ('id' => 13, 'tasks' => 0, 'tiles' => ''),
    14 => array ('id' => 14, 'tasks' => 4, 'tiles' => 'i1,i2,i3', 'deadzone' => true),
    15 => array ('id' => 15, 'tasks' => 4, 'tiles' => '1,2,3,4'),
    16 => array ('id' => 16, 'tasks' => 0, 'tiles' => ''),
    17 => array ('id' => 17, 'tasks' => 2, 'tiles' => ''),
    18 => array ('id' => 18, 'tasks' => 5, 'tiles' => '', 'disruption' => 2),
    19 => array ('id' => 19, 'tasks' => 5, 'tiles' => '1', 'disruption' => 3),
    20 => array ('id' => 20, 'tasks' => 2, 'tiles' => '', 'down' => true, 'question' => clienttranslate('Are you OK to take the tasks?'), 'replies' => clienttranslate('Yes/No')),
    21 => array ('id' => 21, 'tasks' => 5, 'tiles' => '1,2', 'deadzone' => true),
    22 => array ('id' => 22, 'tasks' => 5, 'tiles' => 'i1,i2,i3,i4'),
    23 => array ('id' => 23, 'tasks' => 5, 'tiles' => '1,2,3,4,5'),
    24 => array ('id' => 24, 'tasks' => 6, 'tiles' => '', 'distribution' => true, 'question' => clienttranslate('Do you want to take the task?'), 'replies' => clienttranslate('Yes/No')),
    25 => array ('id' => 25, 'tasks' => 6, 'tiles' => 'i1,i2', 'deadzone' => true, 'special5' => true),
    26 => array ('id' => 26, 'tasks' => 0, 'tiles' => ''),
    27 => array ('id' => 27, 'tasks' => 3, 'tiles' => '', 'special5' => true, 'down' => true, 'question' => clienttranslate('Are you OK to take the tasks?'), 'replies' => clienttranslate('Yes/No')),
    28 => array ('id' => 28, 'tasks' => 6, 'tiles' => '1,o', 'disruption' => 3, 'special5' => true),
    29 => array ('id' => 29, 'tasks' => 0, 'tiles' => '', 'deadzone' => true),
    30 => array ('id' => 30, 'tasks' => 6, 'tiles' => 'i1,i2,i3', 'disruption' => 2, 'special5' => true),
    31 => array ('id' => 31, 'tasks' => 6, 'tiles' => '1,2,3', 'special5' => true),
    32 => array ('id' => 32, 'tasks' => 7, 'tiles' => '', 'distribution' => true, 'question' => clienttranslate('Do you want to take the task?'), 'replies' => clienttranslate('Yes/No'), 'special5' => true),
    33 => array ('id' => 33, 'tasks' => 0, 'tiles' => '', 'question' => clienttranslate('Do you want to volunteer?'), 'replies' => clienttranslate('Yes/No')),
    34 => array ('id' => 34, 'tasks' => 0, 'tiles' => ''),
    35 => array ('id' => 35, 'tasks' => 7, 'tiles' => 'i1,i2,i3', 'special5' => true),
    36 => array ('id' => 36, 'tasks' => 7, 'tiles' => '1,2', 'distribution' => true, 'question' => clienttranslate('Do you want to take the task?'), 'replies' => clienttranslate('Yes/No'), 'special5' => true),
    37 => array ('id' => 37, 'tasks' => 4, 'tiles' => '', 'special5' => true,'down' => true, 'question' => clienttranslate('Are you OK to take the tasks?'), 'replies' => clienttranslate('Yes/No')),
    38 => array ('id' => 38, 'tasks' => 8, 'tiles' => '', 'disruption' => 3, 'special5' => true),
    39 => array ('id' => 39, 'tasks' => 8, 'tiles' => 'i1,i2,i3', 'deadzone' => true, 'special5' => true),
    40 => array ('id' => 40, 'tasks' => 8, 'tiles' => '1,2,3', 'special5' => true),
    41 => array ('id' => 41, 'tasks' => 0, 'tiles' => '',  'question' => clienttranslate('Are you ready?'), 'replies' => clienttranslate('Yes/No')),
    42 => array ('id' => 42, 'tasks' => 9, 'tiles' => '', 'special5' => true),
    43 => array ('id' => 43, 'tasks' => 9, 'tiles' => '', 'distribution' => true, 'question' => clienttranslate('Do you want to take the task?'), 'replies' => clienttranslate('Yes/No'), 'special5' => true),
    44 => array ('id' => 44, 'tasks' => 0, 'tiles' => ''),
    45 => array ('id' => 45, 'tasks' => 9, 'tiles' => 'i1,i2,i3', 'special5' => true),
    46 => array ('id' => 46, 'tasks' => 0, 'tiles' => ''),
    47 => array ('id' => 47, 'tasks' => 10, 'tiles' => '', 'special5' => true),
    48 => array ('id' => 48, 'tasks' => 3, 'tiles' => 'o', 'special5' => true),
    49 => array ('id' => 49, 'tasks' => 10, 'tiles' => 'i1,i2,i3', 'special5' => true),
    50 => array ('id' => 50, 'tasks' => 0, 'tiles' => '', 'question' => clienttranslate('What is your preferred task?'), 'replies' => clienttranslate('Only the first four tricks/All tricks in between/Only the last trick'))
);

$this->specificCheck = [5,9,13,16,17,26,29,33,34,41,44,46,48,50];


