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
 * gameoptions.inc.php
 *
 * Hearts game options description
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

/**
 * Note: this code is modified to add suggestions from BGA players and popular variants.
 * Please visit here to read the basic code used in the BGA Studio tutorial: https://github.com/elaskavaia/
 */

$custom_only = ['type' => 'otheroption', 'id' => 101, 'value' => 100]; // Hide customization options
$game_options = [
    100 => [
        'name' => totranslate('Initial points'),
        'values' => [
            0 => [
                'name' => '50',
                'tmdisplay' => totranslate('50 points'),
            ],
            1 => [
                'name' => '75',
                'tmdisplay' => totranslate('75 points'),
            ],
            2 => [
                'name' => '100',
                'tmdisplay' => totranslate('100 points'),
            ],
        ],
    ],

    101 => [
        'name' => totranslate('Rule set'),
        'values' => [
            0 => [
                'name' => totranslate('Hearts'),
                'tmdisplay' => totranslate('Hearts'),
            ],
            1 => [
                'name' => totranslate('Omnibus Hearts'),
                'description' => totranslate('Capturing the Jack of Diamonds adds bonus 10 points.'),
                'tmdisplay' => totranslate('Omnibus Hearts'),
            ],
            2 => [
                'name' => totranslate('Black Maria'),
                'description' => totranslate('The King of Spades scores 10 penalty points. The Ace of Spades scores 7 penalty points. There is no restriction on penalty card plays. Cards are always passed to the right side. Shooting the moon does not exist. The left player of the dealer starts the first trick with any card.'),
                'tmdisplay' => totranslate('Black Maria'),
            ],
            3 => [
                'name' => totranslate('Spot Hearts'),
                'description' => totranslate('The penalty point of a Heart is equal to its rank (2 = 2 points, ..., A = 14 points). The Queen of Spades scores 25 penalty points. The initial score is multiplied by 5.'),
                'tmdisplay' => totranslate('Spot Hearts'),
            ],
            100 => [
                'name' => totranslate('Custom'),
                'description' => totranslate('Table creator can customize variant options.'),
            ],
        ],
    ],

    102 => [
        'name' => totranslate('Jack of Diamonds bonus'),
        'values' => [
            0 => ['name' => totranslate('Disabled')],
            1 => [
                'name' => totranslate('Enabled'),
                'description' => totranslate('Capturing the Jack of Diamonds adds bonus 10 points.'),
                'tmdisplay' => totranslate('Jack of Diamonds bonus'),
            ],
        ],
        'displaycondition' => [$custom_only],
    ],

    103 => [
        'name' => totranslate('Penalty card play limit'),
        'values' => [
            0 => [
                'name' => totranslate('Default'),
                'description' => totranslate('Penalty cards cannot be played in the first trick unless all cards in hand are penalty cards. Any Heart cannot be played as a lead card until someone plays a Heart.'),
            ],
            1 => [
                'name' => totranslate('Points in the first trick'),
                'description' => totranslate('Players may play penalty cards during the first trick.'),
                'tmdisplay' => totranslate('Points in the first trick'),
            ],
            2 => ['name' => totranslate('Disabled'), 'tmdisplay' => totranslate('No penalty card play limit')],
        ],
        'displaycondition' => [$custom_only],
    ],

    104 => [
        'name' => totranslate('Face value scoring'),
        'values' => [
            0 => ['name' => totranslate('Disabled')],
            1 => [
                'name' => totranslate('Enabled'),
                'description' => totranslate('The penalty point of a Heart is equal to its rank (2 = 2 points, ..., A = 14 points). The Queen of Spades scores 25 penalty points. If enabled, the King of Spades scores 20 penalty points, the Ace of Spades scores 15 penalty points, the Jack of Diamonds scores 20 bonus points. The initial score is multiplied by 5.'),
                'tmdisplay' => totranslate('Face value scoring'),
            ],
        ],
        'displaycondition' => [$custom_only],
    ],

    105 => [
        'name' => totranslate('High Spades scoring'),
        'values' => [
            0 => ['name' => totranslate('Disabled')],
            1 => [
                'name' => totranslate('Enabled'),
                'description' => totranslate('The King of Spades scores 10 penalty points. The Ace of Spades scores 7 penalty points.'),
                'tmdisplay' => totranslate('High Spades scoring'),
            ],
        ],
        'displaycondition' => [$custom_only],
    ],

    106 => [
        'name' => totranslate('Starter card'),
        'values' => [
            0 => [
                'name' => totranslate('Enabled'),
                'description' => totranslate('The lowest Club starts the first trick.'),
            ],
            1 => [
                'name' => totranslate('Disabled'),
                'description' => totranslate('The left player of the dealer starts the first trick with any card.'),
                'tmdisplay' => totranslate('No starter card'),
            ],
        ],
        'displaycondition' => [$custom_only],
    ],

    107 => [
        'name' => totranslate('Shooting the moon'),
        'values' => [
            0 => [
                'name' => totranslate('Default'),
                'description' => totranslate('Capturing all penalty cards deducts points from each player except the shooter.'),
            ],
            1 => [
                'name' => totranslate('Positive moon scoring'),
                'description' => totranslate('Capturing all penalty cards adds bonus points to the shooter.'),
                'tmdisplay' => totranslate('Positive moon scoring'),
            ],
            2 => [
                'name' => totranslate('Disabled'),
                'tmdisplay' => totranslate('No shooting the moon'),
            ],
        ],
        'displaycondition' => [$custom_only],
    ],

    108 => [
        'name' => totranslate('Pass cycle'),
        'values' => [
            0 => ['name' => totranslate('Default')],
            1 => [
                'name' => totranslate('Remove no pass hands'),
                'description' => totranslate('Remove \'no pass\' hands from passing cycle.'),
                'tmdisplay' => totranslate('Remove no pass hands'),
            ],
            2 => [
                'name' => totranslate('Always pass right'),
                'description' => totranslate('Cards are always passed to the right side.'),
                'tmdisplay' => totranslate('Always pass right'),
            ],
        ],
        'displaycondition' => [$custom_only],
    ],

    109 => [
        'name' => totranslate('Count hand scores'),
        'values' => [
            0 => ['name' => totranslate('Disabled')],
            1 => [
                'name' => totranslate('Enabled'),
                'description' => totranslate('Display captured points during the hand.'),
                'tmdisplay' => totranslate('Count hand scores'),
            ],
        ],
        'startcondition' => [
            // Remove the option from Arena mode as this option is designed for beginners and turn-based mode players
            1 => [
                [
                    'type' => 'otheroptionisnot',
                    'id' => 201, // Game mode framework option
                    'value' => 2, // 2 = Arena mode
                    'message' => totranslate('This option cannot be selected in Arena mode'),
                ],
            ],
        ],
        'level' => 'additional',
    ],

    110 => [
        'name' => totranslate('Skip uneventful final tricks'),
        'values' => [
            0 => ['name' => totranslate('Yes')],
            1 => [
                'name' => totranslate('No'),
                'tmdisplay' => totranslate('Always play all tricks'),
            ],
        ],
        'level' => 'additional',
    ],
];

$game_preferences = [
    100 => [
        'name' => totranslate('Card style'),
        'needReload' => true,
        'values' => [
            1 => ['name' => totranslate('Old')],
            2 => ['name' => totranslate('Small indexes')],
            3 => ['name' => totranslate('Large indexes')],
            4 => ['name' => totranslate('Cartoonish')],
        ],
    ],
    101 => [
        'name' => totranslate('Overlap cards in hand'),
        'needReload' => true,
        'values' => [
            1 => ['name' => totranslate('Disabled')],
            2 => ['name' => totranslate('Enabled')],
        ],
    ],
    102 => [
        'name' => totranslate('Display confirmation'),
        'needReload' => true,
        'values' => [
            1 => ['name' => totranslate('Disabled')],
            2 => ['name' => totranslate('Enabled')],
        ],
    ],
    103 => [
        'name' => totranslate('Play sound effects'),
        'needReload' => true,
        'values' => [
            1 => ['name' => totranslate('Yes')],
            2 => ['name' => totranslate('No')],
        ],
    ],
    104 => [
        'name' => totranslate('Highlight unplayable cards'),
        'needReload' => true,
        'values' => [
            1 => ['name' => totranslate('Yes')],
            2 => ['name' => totranslate('No')],
        ],
        'default' => 2,
    ],
];