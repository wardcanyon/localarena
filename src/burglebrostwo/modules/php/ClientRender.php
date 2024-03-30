<?php

namespace BurgleBrosTwo;

require_once "card_data.inc.php";

use BurgleBrosTwo\Models\PlayerCharacter;

// Utilities that produce data in the format expected by the client
// (i.e. the TypeScript types specified in "burglebrostwo.d.ts").
trait ClientRender
{
    // XXX: There is not yet a TypeScript `Card` type.  There *is* a
    // `Card` interface but this doesn't seem to match; should clean
    // that situation up.
    private function renderCardForClient($card)
    {
        return [
            "id" => intval($card["id"]),
            "cardTypeGroup" => $card["card_type_group"],
            "cardType" => $card["card_type"],
            "cardLocation" => $card["card_location"],
            "cardSublocation" => $card["card_sublocation"],
            "cardLocationIndex" => $card["card_location_index"],
            "useCount" => $card["use_count"],
            // XXX: this is a hardwired assumption that may not be safe to always make
            "cardImage" => [$card["card_type_group"], $card["card_type"]],
        ];
    }

    private function renderCardsForClient($cards)
    {
        $result = [];
        foreach ($cards as $card) {
            $result[] = self::renderCardForClient($card);
        }
        return $result;
    }

    // Returns a value of the TypeScript `Entity` type.
    private function renderEntityForClient($entity)
    {
          if (is_null($entity["pos_x"])) {
            // Don't show entities that aren't positioned on the board.
            return null;
        }

        $result = [
            "id" => intval($entity["id"]),
            "state" => $entity["state"],
            "pos" => $this->renderPosForClient($entity),
            "entityClass" => self::getEntityClass($entity["entity_type"]),
        ];

        switch ($result["entityClass"]) {
            case ENTITYCLASS_CHIP:
                if ($entity["state"] == "VISIBLE") {
                    $result["entityType"] = $entity["entity_type"];
                }
                break;
            case ENTITYCLASS_DESTINATION:
            case ENTITYCLASS_TOKEN:
                $result["entityType"] = $entity["entity_type"];
                break;
            case ENTITYCLASS_CHARACTER:
                $result["entityType"] = $entity["entity_type"];

                // XXX: This is just enough information to render the
                // entity.  We could also only provide the ID, and have
                // the client look at the separate character state info we
                // send (or will eventually send).

                switch ($entity["entity_type"]) {
                    case ENTITYTYPE_CHARACTER_PLAYER:
                        $playerCharacter = self::rawGetPlayerCharacterByEntityId(
                            intval($entity["id"])
                        );
                        $result["character"] = [
                            "characterType" => "player",
                            "id" => intval($playerCharacter["id"]),
                            "appearance" =>
                                "meeple_bros_" . $playerCharacter["bro"],
                        ];
                        break;
                    case ENTITYTYPE_CHARACTER_TIGER:
                    case ENTITYTYPE_CHARACTER_BOUNCER:
                        $npc = self::rawGetNpcByEntityId(intval($entity["id"]));
                        $result["character"] = [
                            "characterType" => "npc",
                            "id" => intval($npc["id"]),
                            // XXX: Some NPCs (e.g. the tiger) have different appearances.
                            //
                            // XXX: Should "appearance" just be a top-level
                            // entity property, rather than something in the
                            // "character" submessage?
                            "appearance" => "meeple_bouncer",
                        ];
                        break;
                    default:
                        throw new \feException(
                            "Unexpected ENTITYTYPE_* for ENTITYCLASS_CHARACTER."
                        );
                }

                break;
            default:
                throw new Exception("Unexpected entity class.");
        }

        return $result;
    }

    private function renderEntitiesForClient($entities)
    {
        $result = [];
        foreach ($entities as $entity) {
            $rendered_entity = $this->renderEntityForClient($entity);
            if (!is_null($rendered_entity)) {
                $result[] = $rendered_entity;
            }
        }
        return $result;
    }

    // Returns a value of the TypeScript `Wall` type.
    private function renderWallForClient($wall)
    {
        return [
            "id" => intval($wall["id"]),
            "pos" => $this->renderPosForClient($wall),
            "vertical" => boolval($wall["vertical"]),
        ];
    }

    // Returns a value of the TypeScript `Position` type.
    private function renderPosForClient($thing)
    {
        return [
            intval($thing["pos_x"]),
            intval($thing["pos_y"]),
            intval($thing["pos_z"]),
        ];
    }

    private function renderWallsForClient($walls)
    {
        $result = [];
        foreach ($walls as $wall) {
            $rendered_wall = $this->renderWallForClient($wall);
            if (!is_null($rendered_wall)) {
                $result[] = $rendered_wall;
            }
        }
        return $result;
    }

    private function renderStatusesForClient($statuses)
    {
        return $statuses;
    }

    private function renderPlayerCharacterForClient($pc)
    {
        if ($pc instanceof PlayerCharacter) {
            $pc = $pc->row;
        }

        $pc_name = CARD_DATA["bros"][$pc["bro"]]["title"];
        $pc_id = intval($pc["id"]);
        $player_id = intval($pc["player_id"]);
        $player = $this->rawGetPlayer($player_id);

        $hand = new \BurgleBrosTwo\Managers\CardManager("CHARACTER", $pc_id);
        $hand_cards = $hand->getAll(["HAND", "PREPPED"]);
        // XXX: This means that there's no way to see the contents of
        // the DISCARD sublocation (at least through this data).

        return [
            "id" => $pc_id,
            "entityId" => intval($pc["entity_id"]),
            "state" => $pc["state"],
            "turnOrder" => intval($pc["turn_order"]),
            "playerId" => $player_id,
            "heat" => intval($pc["heat"]),
            "characterName" => $pc_name,
            "playerName" => $player["player_name"],
            "hand" => $this->renderCardsForClient($hand_cards),
            "statuses" => $this->renderStatusesForClient($pc["statuses"]),
        ];
    }

    private function renderPlayerCharactersForClient($pcs)
    {
        $result = [];
        foreach ($pcs as $pc) {
            $rendered_pc = $this->renderPlayerCharacterForClient($pc);
            if (!is_null($rendered_pc)) {
                $result[] = $rendered_pc;
            }
        }
        return $result;
    }

    // Returns a value of the TypeScript `FloorMap` type.
    private function renderFloorMapForClient(int $z, $tiles, $walls)
    {
        $tiles = array_filter($tiles, function ($row) use ($z) {
            return intval($row["pos_z"]) == $z;
        });
        $walls = array_filter($walls, function ($row) use ($z) {
            return intval($row["pos_z"]) == $z;
        });

        return [
            "width" => 4,
            "height" => 4,
            "tiles" => self::renderTilesForClient($tiles),
            "walls" => self::renderWallsForClient($walls),
        ];
    }

    // Returns a value of the TypeScript `GameMap` type.
    private function renderGameMapForClient($tiles, $walls)
    {
        $result = ["floors" => []];
        for ($z = 0; $z < 2; ++$z) {
            $result["floors"][$z] = $this->renderFloorMapForClient(
                $z,
                $tiles,
                $walls
            );
        }
        return $result;
    }

    private function renderTilesForClient($tiles)
    {
        $result = [];
        foreach ($tiles as $tile) {
            $rendered_tile = $this->renderTileForClient($tile);
            if (!is_null($rendered_tile)) {
                $result[] = $rendered_tile;
            }
        }
        return $result;
    }

    // Returns a value of the TypeScript `Tile` type.
    function renderTileForClient($row)
    {
        $tiledata = [
            "id" => intval($row["id"]),
            "state" => $row["state"],
            "pos" => $this->renderPosForClient($row),
        ];

        if ($tiledata["state"] == "VISIBLE") {
            $tiledata["type"] = $row["tile_type"];
            $tiledata["number"] = $row["tile_number"];
        }

        return $tiledata;
    }

    function renderDeckForClient($mgr)
    {
        return [
            "cardLocation" => $mgr->cardLocation(),
            "cardLocationIndex" => $mgr->cardLocationIndex(),
            "deckCount" => count($mgr->getAll(["DECK"])),
            "discardPile" => self::renderCardsForClient(
                $mgr->getAll(["DISCARD"])
            ),
        ];
    }

    function getAndRenderAllDecksForClient()
    {
        // N.B.: "BROS" is deliberately not sent here; those cards are
        // used for character selection, really.  The "DEADDROP" deck
        // is also not sent, just because it wouldn't be very useful
        // to players.
        $decks = [];
        foreach (["GEAR", "LOUNGE", "POOL"] as $cardLocation) {
            $decks[$cardLocation] = self::renderDeckForClient(
                new \BurgleBrosTwo\Managers\CardManager($cardLocation)
            );
        }
        $decks["PATROL_0"] = self::renderDeckForClient(
            new \BurgleBrosTwo\Managers\CardManager("PATROL", 0)
        );
        $decks["PATROL_1"] = self::renderDeckForClient(
            new \BurgleBrosTwo\Managers\CardManager("PATROL", 1)
        );
        return $decks;
    }

    function renderFinaleForClient()
    {
        return [
            "state" => "HIDDEN", // one of HIDDEN, INACTIVE, ACTIVE
            // XXX: if !hidden, then some info about the card & etc. here?
        ];
    }

    function renderNpcForClient($npc)
    {
        // XXX: Need to determine this based on 'npc_type'; and maybe
        //   we want to add the bouncer's floor to its name?
        $npc_name = "Bouncer";
        $npc_id = intval($npc["id"]);
        $entity_id = intval($npc["entity_id"]);
        $npc_entity = $this->getEntity($entity_id);

        $destination_entity = null;
        $destination_entity_id = null;
        $patrol_path = null;
        if (!is_null($npc["destination_entity_id"])) {
            $destination_entity_id = intval($npc["destination_entity_id"]);
            $destination_entity = $this->getEntity($destination_entity_id);

            // XXX: We should be able to avoid repeated map reads
            // through memoization.
            $map = $this->readMap($npc_entity->pos->z);
            $path = $map->shortestPathClockwise(
                $npc_entity->pos,
                $destination_entity->pos
            );
        }

        return [
            "id" => $npc_id,
            "entityId" => $entity_id,
            "characterName" => $npc_name,
            "type" => $npc["npc_type"],
            "destinationEntityId" => $destination_entity_id,
            "patrolPath" => $patrol_path,
            "movement" => 4,
            "statuses" => $this->renderStatusesForClient($npc["statuses"]),
        ];
    }

    function renderNpcsForClient($npcs)
    {
        $result = [];
        foreach ($npcs as $npc) {
            $rendered_npc = $this->renderNpcForClient($npc);
            if (!is_null($rendered_npc)) {
                $result[] = $rendered_npc;
            }
        }
        return $result;
    }
}
