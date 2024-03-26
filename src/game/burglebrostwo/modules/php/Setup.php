<?php

namespace BurgleBrosTwo;

// This code performs the setup that's done as the table is created,
// before the pre-game states where players choose characters, place
// entrance tokens, and so on.  The remainder of setup is performed in
// ST_FINISH_SETUP just before the characters take their first turns
// and enter the map.
trait Setup
{
    protected function setupNewGame($players, $options = [])
    {
        echo "*** BB2: setupNewGame() call\n";

        self::initPlayers($players);
        self::initBoard();
        self::initCards();

        $npc_stepping = [];
        foreach ($players as $player_id => $player) {
            $npc_stepping[$player_id] = false;
        }
        $this->setGameStateJSON(GAMESTATE_JSON_NPC_STEPPING, $npc_stepping);

        $this->setGameStateJSON(GAMESTATE_JSON_RESOLVE_STACK, []);
        $this->setGameStateJSON(GAMESTATE_JSON_RESOLVE_VALUE_STACK, []);
        $this->setGameStateJSON(GAMESTATE_JSON_TABLE_STATUSES, []);

        echo "*** BB2: set game json\n";

        // XXX: need to replace these
        // Init stats
        self::initStat("player", "discPlayedOnCorner", 0);
        self::initStat("player", "discPlayedOnBorder", 0);
        self::initStat("player", "discPlayedOnCenter", 0);
        self::initStat("player", "turnedOver", 0);

        // Active first player
        self::activeNextPlayer();

        echo "*** BB2: setupNewGame() done\n";

    }

    // N.B.: This sets up information about the human *players*.
    // Character ("bro") setup is done during ST_CHOOSE_CHARACTERS.
    private function initPlayers($players)
    {
        // Set the colors of the players with HTML color code.
        //
        // The default below is red/green/blue/orange/brown.
        //
        // The number of colors defined here must correspond to the
        // maximum number of players allowed for the game.
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos["player_colors"];

        $sql =
            "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = [];
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] =
                "('" .
                $player_id .
                "','" .
                $color .
                "','" .
                $player["player_canal"] .
                "','" .
                addslashes($player["player_name"]) .
                "','" .
                addslashes($player["player_avatar"]) .
                "')";
        }
        $sql .= implode(",", $values);
        self::DbQuery($sql);
        self::reattributeColorsBasedOnPreferences(
            $players,
            $gameinfos["player_colors"]
        );
        self::reloadPlayersBasicInfos();
    }

    private function initBoard()
    {
        $tiles_misc = [
            ["type" => "BUFFET", "number" => 5],
            ["type" => "BUFFET", "number" => 5],
            ["type" => "CASHIER-CAGES", "number" => 5],
            ["type" => "CASHIER-CAGES", "number" => 6],
            ["type" => "COUNT-ROOM", "number" => 1],
            ["type" => "COUNT-ROOM", "number" => 2],
            ["type" => "CROWS-NEST", "number" => 1],
            ["type" => "CROWS-NEST", "number" => 2],
            ["type" => "FRONT-DESK", "number" => 1],
            ["type" => "FRONT-DESK", "number" => 2],
            ["type" => "LOUNGE", "number" => 3],
            ["type" => "LOUNGE", "number" => 4],
            ["type" => "MAGIC-SHOW", "number" => 3],
            ["type" => "MAGIC-SHOW", "number" => 4],
            ["type" => "PIT-BOSS", "number" => 6],
            ["type" => "PIT-BOSS", "number" => 6],
            ["type" => "POOL", "number" => 1],
            ["type" => "POOL", "number" => 2],
            ["type" => "REVOLVING-DOOR", "number" => 5],
            ["type" => "REVOLVING-DOOR", "number" => 5],
            ["type" => "SLOTS", "number" => 3],
            ["type" => "SLOTS", "number" => 4],
            ["type" => "SURVEILLANCE", "number" => 3],
            ["type" => "SURVEILLANCE", "number" => 4],
            ["type" => "TABLE-GAMES", "number" => 5],
            ["type" => "TABLE-GAMES", "number" => 6],
        ];

        $tiles_monorail = [
            ["type" => "MONORAIL", "number" => 1],
            ["type" => "MONORAIL", "number" => 2],
        ];

        $tiles_escalator = [
            ["type" => "ESCALATOR", "number" => 3],
            ["type" => "ESCALATOR", "number" => 4],
        ];

        shuffle($tiles_misc);
        shuffle($tiles_monorail);
        shuffle($tiles_escalator);

        $tiles_floor[0] = array_merge(
            [["type" => "OWNERS-OFFICE"]],
            array_slice($tiles_misc, 0, 13),
            array_slice($tiles_monorail, 0, 1),
            array_slice($tiles_escalator, 0, 1)
        );
        $tiles_floor[1] = array_merge(
            [["type" => "SAFE"]],
            array_slice($tiles_misc, 13, 13),
            array_slice($tiles_monorail, 1, 1),
            array_slice($tiles_escalator, 1, 1)
        );

        shuffle($tiles_floor[0]);
        shuffle($tiles_floor[1]);

        for ($z = 0; $z < 2; ++$z) {
            for ($x = 0; $x < 4; ++$x) {
                for ($y = 0; $y < 4; ++$y) {
                    $tilespec = array_pop($tiles_floor[$z]);

                    // XXX: temporarily creating all visible for client work
                    $sql =
                        "INSERT INTO tile (`pos_x`, `pos_y`, `pos_z`, `state`, `tile_type`, `tile_number`) VALUES " .
                        "($x, $y, $z, \"HIDDEN\", \"" .
                        $tilespec["type"] .
                        "\", " .
                        (array_key_exists("number", $tilespec)
                            ? $tilespec["number"]
                            : "NULL") .
                        ")";
                    self::DbQuery($sql);
                }
            }
        }

        // TODO: Assert that the $tiles_* arrays are all empty.
    }

    // Use the static data created by the preprocessor to populate
    // initial decks.
    private function initCards()
    {
        self::initCardsPatrol();

        // N.B.: We create actual "bros" cards just so that we can use
        // card IDs to refer to them during character selection.
        foreach (
            ["bros", "gear", "lounge", "pool", "deaddrops"]
            as $card_type_group
        ) {
            $values = [];
            foreach (
                CARD_DATA[$card_type_group]
                as $card_type => $card_type_data
            ) {
                $values[] =
                    '("' .
                    $card_type_group .
                    '", "' .
                    $card_type .
                    '", "' .
                    strtoupper($card_type_group) .
                    '", "DECK", -1)';
            }
            // N.B.: We do this just to make sure the that the
            // server-side IDs are arbitrary.
            shuffle($values);
            $sql =
                "INSERT INTO card (`card_type_group`, `card_type`, `card_location`, `card_sublocation`, `card_order`) VALUES " .
                implode(",", $values);
            self::DbQuery($sql);
        }
    }

    private function initCardsPatrol_XXX_old()
    {
        // Positive numbers mean "add this many distracted cards";
        // negative ones mean "discard this many patrol cards".
        $suspicion_effects = [
            [3, 3, 2, 2, 1, 1, -1, -1, -2, -2],
            [3, 3, 2, 1, 1, 0, 0, -1, -1, -2],
        ];

        $values = [];
        for ($x = 0; $x < 4; $x++) {
            for ($y = 0; $y < 4; $y++) {
                for ($z = 0; $z < 2; $z++) {
                    $card_type = "patrol_{$x}_{$y}_{$z}";
                    $values[] =
                        '("patrol", "' .
                        $card_type .
                        '", "PATROL", "DECK", ' .
                        $z .
                        ")";
                }
            }
        }

        // Add Distracted card(s), if necessary.
        for ($z = 0; $z < 2; $z++) {
            $effect = $suspicion_effects[$z][self::optionSuspicion() - 1];
            for ($i = 0; $i < $effect; ++$i) {
                $values[] =
                    '("patrol", "distracted", "PATROL", "DECK", ' . $z . ", -1)";
            }
        }

        shuffle($values);
        $sql =
            "INSERT INTO card (`card_type_group`, `card_type`, `card_location`, `card_sublocation`, `card_location_index`, `card_order`) VALUES " .
            implode(",", $values);
        self::DbQuery($sql);

        // // Discard patrol card(s), if necessary.
        // for ($z = 0; $z < 2; $z++) {
        //     $patrolDeck = new CardManager('PATROL', $z);

        //     $effect = $suspicion_effects[$z][self::optionSuspicion()-1];
        //     for ($i = 0; $i > $effect; --$i) {
        //         // XXX: print return value (which is a Card) for debug purposes?
        //         $patrolDeck.drawAndDiscard();
        //     }
        // }
    }

    private function initCardsPatrol()
    {
        // Positive numbers mean "add this many distracted cards";
        // negative ones mean "discard this many patrol cards".
        $suspicion_effects = [
            [3, 3, 2, 2, 1, 1, -1, -1, -2, -2],
            [3, 3, 2, 1, 1, 0, 0, -1, -1, -2],
        ];

        // One array for cards we want to create in the patrol deck
        // for each floor.
        $card_specs = [[], []];
        for ($x = 0; $x < 4; $x++) {
            for ($y = 0; $y < 4; $y++) {
                for ($z = 0; $z < 2; $z++) {
                    $card_specs[$z][] = [
                        "card_type" => "patrol_{$x}_{$y}_{$z}",
                    ];
                }
            }
        }

        $patrol_deck = [
            new \BurgleBrosTwo\Managers\CardManager("PATROL", 0),
            new \BurgleBrosTwo\Managers\CardManager("PATROL", 1),
        ];

        for ($z = 0; $z < 2; $z++) {
            // Add Distracted card(s), if necessary.
            $effect = $suspicion_effects[$z][self::optionSuspicion() - 1];
            for ($i = 0; $i < $effect; ++$i) {
                $card_specs[$z][] = ["card_type" => "distracted"];
            }

            // Create deck.
            //self::trace("Creating patrol deck for z={$z}...");
            $patrol_deck[$z]->createCards($card_specs[$z]);
            $patrol_deck[$z]->shuffle();

            // // XXX: This was just to test functionality.
            // for ($i = 0; $i < 3; ++$i) {
            //     $patrol_deck[$z]->drawAndDiscard();
            // }
        }

        // Discard patrol card(s), if necessary.
        for ($z = 0; $z < 2; $z++) {
            $effect = $suspicion_effects[$z][self::optionSuspicion() - 1];
            for ($i = 0; $i > $effect; --$i) {
                // Discard a normal patrol card, shuffling if the top
                // card is something else (e.g. a Distracted card).
                $top_card = null;
                while (
                    $top_card == null ||
                    !str_starts_with($top_card . cardType, "patrol_")
                ) {
                    $patrol_deck[$z]->shuffle();
                    $top_card = $patrol_deck[$z]->peekTop();
                }
                $patrol_deck[$z]->drawAndDiscard();
            }
        }
    }
}
