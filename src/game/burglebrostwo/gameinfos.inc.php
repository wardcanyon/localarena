<?php

$gameinfos = [
    // Game publisher
    "publisher" => "Fowers Games",

    // Url of game publisher website
    "publisher_website" => "https://www.fowers.games/",

    // Board Game Geek ID of the publisher
    "publisher_bgg_id" => 34669,

    // Board game geek if of the game
    "bgg_id" => 286537,

    // Players configuration that can be played (ex: 2 to 4 players)
    "players" => [1, 2, 3, 4],

    // Suggest players to play with this number of players. Must be null if there is no such advice, or if there is only one possible player configuration.
    "suggest_player_number" => null,

    // Discourage players to play with this number of players. Must be null if there is no such advice.
    "not_recommend_player_number" => null,

    // Estimated game duration, in minutes (used only for the launch, afterward the real duration is computed)
    "estimated_duration" => 10,

    // Time in second add to a player when "giveExtraTime" is called (speed profile = fast)
    "fast_additional_time" => 7,

    // Time in second add to a player when "giveExtraTime" is called (speed profile = medium)
    "medium_additional_time" => 16,

    // Time in second add to a player when "giveExtraTime" is called (speed profile = slow)
    "slow_additional_time" => 23,

    // Game is "beta". A game MUST set is_beta=1 when published on BGA for the first time, and must remains like this until all bugs are fixed.
    "is_beta" => 1,

    // Is this game cooperative (all players wins together or loose together)
    "is_coop" => 1,

    // Colors attributed to players.
    "player_colors" => ["ff0000", "999999", "0000ff", "773300"],

    // Favorite colors support: if set to "true", support attribution of
    // favorite colors based on player's preferences (see
    // `reattributeColorsBasedOnPreferences()` PHP method).
    "favorite_colors_support" => true,

    // Game interface width range (pixels)
    // Note: game interface = space on the left side, without the column on the right
    "game_interface_width" => [
        // Minimum width
        //  default: 760
        //  maximum possible value: 760 (ie: your game interface should fit with a 760px width (correspond to a 1024px screen)
        //  minimum possible value: 320 (the lowest value you specify, the better the display is on mobile)
        "min" => 760,

        // Maximum width
        //  default: null (ie: no limit, the game interface is as big as the player's screen allows it).
        //  maximum possible value: unlimited
        //  minimum possible value: 760
        "max" => null,
    ],

    "custom_buy_button" => [
        "url" =>
            "https://www.fowers.games/collections/direct-price/products/burgle-bros-2",
        "label" => "Fowers Games",
    ],
];
