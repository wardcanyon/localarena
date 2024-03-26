<?php

namespace BurgleBrosTwo\States;

use BurgleBrosTwo\Models\Position;

trait FinishSetup
{
    function stFinishSetup()
    {
        // // XXX: This is not yet called.  This needs to happen once the
        // // players finish pre-game setup (selecting characters, possibly
        // // re-rolling walls, and so on).
        // private function finishSetup() {
        //     // XXX: this needs to pull appropriate cards from the
        //     // GEAR.DECK location to CHARACTER.HAND locations (if not
        //     // using dead-drops)
        //
        //     // XXX: this needs to shuffle the remaining decks (though we
        //     // currently create the cards in shuffled order)
        //
        //     // XXX: this needs to randomly draw a card from the
        //     // DEADDROPS.DECK location and move it to each player's
        //     // CHARACTER.HAND[idx] location (if we are using deaddrops)
        //
        //     // XXX: this needs to place chips and draw initial bouncer
        //     // locations and destinations from each patrol deck
        // }

        // TODO: This will eventually move into the ST_PLACE_WALLS state.
        self::randomlyPlaceWalls();

        self::finishSetupGear();
        self::finishSetupBouncers();
        self::finishSetupChips();

        self::finishSetupTurnOrder();

        self::finishSetupGameState();

        $this->gamestate->nextState("tDone");
    }

    function getAllValidWallPositions($z)
    {
        $wallPositions = [];
        for ($x = 0; $x < 3; ++$x) {
            for ($y = 0; $y < 3; ++$y) {
                $wallPositions[] = ["pos" => [$x, $y, $z], "vertical" => true];
                $wallPositions[] = ["pos" => [$x, $y, $z], "vertical" => false];
            }
            $wallPositions[] = ["pos" => [$x, 3, $z], "vertical" => true];
        }
        for ($y = 0; $y < 3; ++$y) {
            $wallPositions[] = ["pos" => [3, $y, $z], "vertical" => false];
        }
        if (count($wallPositions) != 24) {
            throw new \feException(
                "oops, didn't generate all valid wall positions"
            );
        }

        // XXX: This is an ugly hack; the `Map` class is expecting
        // this to look like it just came out of the database.  Should
        // fix this in a principled way (e.g. with typing).
        foreach ($wallPositions as $i => $wallPos) {
            $wallPositions[$i]["pos_x"] = $wallPos["pos"][0];
            $wallPositions[$i]["pos_y"] = $wallPos["pos"][1];
            $wallPositions[$i]["pos_z"] = $wallPos["pos"][2];
        }

        return $wallPositions;
    }

    function randomlyPlaceWalls()
    {
        for ($z = 0; $z < 2; ++$z) {
            $wallPositions = [];
            do {
                $wallPositions = $this->getAllValidWallPositions($z);
                shuffle($wallPositions);
                $wallPositions = array_slice(
                    $wallPositions,
                    0,
                    WALLS_PER_FLOOR
                );

                // We need to make sure that our randomly-placed walls
                // haven't made any parts of the map unreachable.
                $m = new \BurgleBrosTwo\Utilities\Map(
                    /*tiles=*/ [],
                    $wallPositions
                );
            } while (!$m->isConnected());

            $values = [];
            foreach ($wallPositions as $wallPos) {
                $values[] =
                    "(" .
                    $wallPos["pos"][0] .
                    "," .
                    $wallPos["pos"][1] .
                    "," .
                    $wallPos["pos"][2] .
                    "," .
                    ($wallPos["vertical"] ? "true" : "false") .
                    ")";
            }
            self::DbQuery(
                "INSERT INTO wall (pos_x, pos_y, pos_z, vertical) VALUES " .
                    implode(",", $values)
            );
        }

        // Notify clients.
        $walls = array_values($this->getWalls());
        foreach ($walls as $wall) {
            $this->notifyWallSpawns($wall, /*msg=*/ "", /*silent=*/ true);
        }
    }

    function finishSetupGameState()
    {
        // N.B.: Turn-order-related gamestate variables are set in
        // `finishSetupTurnOrder()`.
    }

    // If necessary (depending on variant), give characters their
    // starting gear cards.
    function finishSetupGear()
    {
        // TODO:
    }

    // Randomly select and place chips.
    //
    // TODO: Should we avoid placing chips on the bouncer's position?
    function finishSetupChips()
    {
        $chips = array_merge(
            array_fill(0, 4, ENTITYTYPE_CHIP_DRUNK),
            array_fill(0, 1, ENTITYTYPE_CHIP_SALESWOMAN),
            array_fill(0, 4, ENTITYTYPE_CHIP_MOLE),
            array_fill(0, 2, ENTITYTYPE_CHIP_CROWD),
            array_fill(0, 2, ENTITYTYPE_CHIP_UNDERCOVER),
            array_fill(0, 3, ENTITYTYPE_CHIP_PRIMADONNA)
        );

        if (count($this->rawGetPlayerCharacters()) > 2) {
            $chips[] = ENTITYTYPE_CHIP_SALESWOMAN;
            shuffle($chips);
            array_pop($chips);
        }

        shuffle($chips);
        assert(count($chips) == 16);

        self::placeChips(/*z=*/ 0, array_slice($chips, 0, 8));
        self::placeChips(/*z=*/ 1, array_slice($chips, 8, 16));
    }

    // $z is a zero-indexed floor number.  $chipTypes is an array of
    // ENTITYTYPE_CHIP_* values.
    function placeChips($z, $chipTypes)
    {
        $positions = [];
        for ($x = 0; $x < 4; ++$x) {
            for ($y = 0; $y < 4; ++$y) {
                $positions[] = [$x, $y, $z];
            }
        }
        shuffle($positions);
        assert(count($chipTypes) <= count($positions));

        foreach ($chipTypes as $i => $chipType) {
            $pos = array_pop($positions);
            self::createEntity(
                $chipType,
                Position::fromArray($pos),
                /*msg=*/ "",
                /*state=*/ "HIDDEN",
                /*silent=*/ true
            );
        }

        // $values = array();
        // foreach ($chipTypes as $i => $chipType) {
        //     $pos = array_pop($positions);
        //     $values[] = '("'.$chipType.'", "HIDDEN", '.$pos[0].', '.$pos[1].', '.$pos[2].')';

        //     self::notifyAllPlayers('entitySpawns', clienttranslate('A chip was placed.'), array(
        //         'silent' => true,
        //         'entity' => self::renderEntityForClient(array(
        //             // N.B.: To avoid needing to read this back, we
        //             // create an array that looks a lot like what we'd
        //             // get from the database if we did.
        //             'id' => self::DbGetLastId(),
        //             'entity_type' => $chipType,
        //             'state' => 'HIDDEN',
        //             'pos_x' => $pos[0],
        //             'pos_y' => $pos[1],
        //             'pos_z' => $pos[2],
        //         )),
        //     ));

        // }
        // self::DbQuery("INSERT INTO entity (entity_type, state, pos_x, pos_y, pos_z) VALUES " . implode(',', $values));
    }

    // Draw patrol cards to set initial bouncer positions and
    // destinations.
    function finishSetupBouncers()
    {
        for ($z = 0; $z < 2; ++$z) {
            $patrol_deck = new \BurgleBrosTwo\Managers\CardManager(
                "PATROL",
                $z
            );

            // Draw for the bouncer's initial position.
            //
            // XXX: We aren't checking for nulls (empty decks) here,
            // which is probably fine, but sloppy.
            //
            // XXX: We need to handle distracted cards here.
            $isNormalPatrolCard = function ($card) {
                return str_starts_with($card["card_type"], "patrol_");
            };
            $spawnPos = $this->posFromPatrolCard(
                $patrol_deck->drawAndDiscardFirstMatching($isNormalPatrolCard)
            );
            $destinationPos = $this->posFromPatrolCard(
                $patrol_deck->drawAndDiscardFirstMatching($isNormalPatrolCard)
            );

            $npcId = $this->spawnNpc(
                "BOUNCER",
                Position::fromArray($spawnPos),
                Position::FromArray($destinationPos)
            );
            // $this->drawBouncerDestination($npcId);
        }
        $this->refreshClientDecks();
        // XXX: we deliberately don't store bouncer pathing info, so that it can't get stale
        // $this->refreshClientBouncers();
    }

    // Send a notification refreshing the visible information that
    // clients have about the various decks.
    function refreshClientDecks()
    {
        self::notifyAllPlayers("decksRefreshed", /*msg=*/ "", [
            "decks" => $this->getAndRenderAllDecksForClient(),
        ]);
    }

    function spawnNpc($npcType, Position $pos, ?Position $destinationPos = null)
    {
        // Don't send a notif to the client yet; we need to finish
        // creating the NPC associated with the entity first.
        //
        // XXX: We're hardwiring that this is a BOUNCER; should fix
        // that.
        $entityId = $this->createEntity(
            ENTITYTYPE_CHARACTER_BOUNCER,
            $pos,
            "",
            /*state=*/ "VISIBLE",
            /*silent=*/ false,
            /*sendNotif=*/ false
        );

        $destinationEntityId = "NULL";
        if (!is_null($destinationPos)) {
            $destinationEntityId = $this->createEntity(
                ENTITYTYPE_DESTINATION,
                $destinationPos,
                "",
                /*state=*/ "VISIBLE",
                /*silent=*/ false,
                /*sendNotif=*/ false
            );
        }

        self::DbQuery(
            "INSERT INTO `character_npc` (`entity_id`, `destination_entity_id`, `npc_type`, `statuses`) VALUES (" .
                $entityId .
                ", " .
                $destinationEntityId .
                ', "' .
                $npcType .
                '", "[]")'
        );
        $npcId = self::DbGetLastId();
        $this->notifyEntitySpawns($this->rawGetEntity($entityId), /*msg=*/ "");
        $this->notifyEntitySpawns(
            $this->rawGetEntity($destinationEntityId),
            /*msg=*/ ""
        );
        return $npcId;
    }
}
