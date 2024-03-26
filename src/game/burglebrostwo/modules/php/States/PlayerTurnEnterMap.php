<?php

namespace BurgleBrosTwo\States;

use BurgleBrosTwo\Models\Position;

trait PlayerTurnEnterMap
{
    function validEnterMapLocations()
    {
        return array_values(
            array_map(function ($row) {
                return $this->posFromRow($row);
            }, $this->getEntities(/*entityType=*/ ENTITYTYPE_TOKEN_ENTRANCE))
        );
    }

    function stPlayerTurnEnterMap()
    {
    }

    function argPlayerTurnEnterMap()
    {
        // XXX: Some of this is shared across all player-character
        // turn states, and should be deduplicated.

        $activeCharacter = $this->rawGetActivePlayerCharacter();

        return [
            "selectableTiles" => $this->validEnterMapLocations(),
            "activeCharacterName" =>
                CARD_DATA["bros"][$activeCharacter["bro"]]["title"],
            "activePlayerName" => $this->getActivePlayerName(),
        ];
    }

    function onActSelectTile_stPlayerTurnEnterMap(Position $pos)
    {
        // XXX: Port this to use `Position`s.
        if (!in_array($pos->toArray(), $this->validEnterMapLocations())) {
            throw new \feException("Selected tile is not valid.");
        }

        $playerCharacter = $this->rawGetActivePlayerCharacter();
        $pcId = intval($playerCharacter["id"]);
        $entityId = intval($playerCharacter["entity_id"]);

        $characterEntity = $this->rawGetEntity($entityId);
        if (!is_null($characterEntity["pos_x"])) {
            throw new \feException("Character has already entered the map!");
        }
        self::DbQuery(
            "UPDATE `entity` SET " .
                $this->buildExprUpdatePos($pos) .
                " WHERE `id` = " .
                $playerCharacter["entity_id"]
        );
        self::DbQuery(
            'UPDATE `character_player` SET state="NORMAL" WHERE `id` = ' .
                $playerCharacter["entity_id"]
        );

        $this->notifyEntitySpawns(
            $this->rawGetEntity($entityId),
            "A character has entered the map!"
        );

        $this->pushOnResolveStack([
            [
                "effectType" => "pc-entering-tile",
                "pcId" => $pcId,
                "pos" => $pos->toArray(),
                // N.B.: This is a special case because the character
                // is entering the map!
                "srcPos" => null,
            ],
        ]);

        // Resolve any effects from entering this tile (including
        // revealing it), and then continue with the rest of the
        // player's first turn, which is like any other turn from here
        // on out.
        $this->gamestate->nextState("tResolveEffect");
    }
}
