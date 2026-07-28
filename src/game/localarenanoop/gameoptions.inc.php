<?php

// The no-op game itself ignores its options; they exist so that
// LocalArena's own tests have a game whose options can be chosen at
// table creation (see tests/GameOptionsTest.php, which names these ids
// and values).
//
// Option 100's value 3 is deliberately commented out.  That is how a
// game ships content it has implemented but does not yet want players
// to select: BGA's lobby offers only the values listed here, while the
// engine honors the value if it is set.  Reaching such a value from a
// test is what `TableParams::$allow_unpublished_option_values` is for,
// so the no-op game needs one to test against.
//
// N.B.: No constants here.  This file is `include`d afresh for every
// table constructed in the process (see `Table::__construct()`), and a
// test may build several.

$game_options = [
    100 => [
        'name' => 'Test option',
        'values' => [
            1 => [
                'name' => 'First',
            ],
            2 => [
                'name' => 'Second',
            ],
            // 3 => [
            //     'name' => 'Third',
            // ],
        ],
        'default' => 1,
    ],
];

$game_preferences = [];
