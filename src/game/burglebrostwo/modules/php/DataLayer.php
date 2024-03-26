<?php

namespace BurgleBrosTwo;

use BurgleBrosTwo\Models\Position;

// -----------
// Database access helpers
// -----------
trait DataLayer
{
    private function getCardsByLocation($location, $sublocation)
    {
        return self::getCollectionFromDb(
            'SELECT * FROM card WHERE card_location="' .
                $location .
                '" AND card_sublocation="' .
                $sublocation .
                '"'
        );
    }

    // Given $card, an associative array returned from the `card`
    // table, returns the static data associated with that card's
    // type.
    function getCardTypeData($card)
    {
        return CARD_DATA[$card["card_type_group"]][$card["card_type"]];
    }

    function getNpcs()
    {
        // XXX: We should make sure that the return value is an associative array indexed by `id`.
        return self::getCollectionFromDB(
            "SELECT * FROM `character_npc` WHERE TRUE"
        );
    }

    function rawGetPlayerCharacters()
    {
        // XXX: We should make sure that the return value is an associative array indexed by `id`.
        return self::getCollectionFromDB(
            "SELECT * FROM `character_player` WHERE TRUE"
        );
    }

    function rawGetPlayerCharacter($characterId)
    {
        return self::getObjectFromDB(
            "SELECT * FROM `character_player` WHERE `id` = " . $characterId
        );
    }

    function rawGetPlayerCharacterByEntityId($entityId)
    {
        return self::getObjectFromDB(
            "SELECT * FROM `character_player` WHERE `entity_id` = " . $entityId
        );
    }

    function rawGetNpc($npcId)
    {
        return self::getObjectFromDB(
            "SELECT * FROM `character_npc` WHERE `id` = " . $npcId
        );
    }

    function rawGetNpcByEntityId($entityId)
    {
        return self::getObjectFromDB(
            "SELECT * FROM `character_npc` WHERE `entity_id` = " . $entityId
        );
    }

    function rawGetPlayer(int $player_id)
    {
        return self::getObjectFromDB(
            "SELECT * FROM `player` WHERE `player_id` = " . $player_id
        );
    }

    function rawGetActivePlayerCharacter()
    {
        // XXX: Should throw an exception if called in a state that is
        // not a "player-character" state, where there is not active
        // player-character and this gamestate variable is
        // meaningless.
        $active_character = $this->getGameStateJson(
            GAMESTATE_JSON_ACTIVE_CHARACTER
        );
        if ($active_character["character_type"] != "PLAYER") {
            throw new \feException("Active character is not a PC!");
        }
        return $this->rawGetPlayerCharacter($active_character["character_id"]);
    }

    // XXX: We should make sure that the return value is an associative array indexed by `id`.
    function getEntities($entityType = null)
    {
        $where_clause = "TRUE";
        if (!is_null($entityType)) {
            $where_clause = 'entity_type = "' . $entityType . '"';
        }
        return self::getCollectionFromDB(
            "SELECT * FROM `entity` WHERE " . $where_clause
        );
    }

    // XXX: Allow a parameter here for entity type, and check that the
    // entity is what we expect to be getting?
    function rawGetEntity($entityId)
    {
        return self::getObjectFromDB(
            "SELECT * FROM `entity` WHERE `id` = " . $entityId
        );
    }

    // XXX: As we "type-ify" things, we should convert the other
    // functions that do raw DB access into "raw..." equivalents with
    // ""entity-izing" wrappers.
    function rawGetEntitiesByPos(Position $pos, $entityClass = null)
    {
        $where_clause = $this->buildExprWherePos($pos);
        $rows = self::getCollectionFromDB(
            "SELECT * FROM `entity` WHERE " . $where_clause
        );
        return array_filter($rows, function ($row) use ($entityClass) {
            $rowEntityClass = $this->getEntityClass($row["entity_type"]);
            return is_null($entityClass) || $rowEntityClass == $entityClass;
        });
    }

    private function getCard($card_id)
    {
        return self::getObjectFromDB(
            "SELECT * FROM card WHERE id = " . $card_id
        );
    }

    function rawGetTiles()
    {
        return self::getCollectionFromDB("SELECT * FROM `tile` WHERE TRUE");
    }

    function rawGetTile($tile_id)
    {
        return self::getObjectFromDB(
            "SELECT * FROM `tile` WHERE id = " . $tile_id
        );
    }

    function rawGetTileByPos(Position $pos)
    {
        return self::getObjectFromDB(
            "SELECT * FROM `tile` WHERE " . self::buildExprWherePos($pos)
        );
    }

    function buildExprUpdatePos(Position $pos)
    {
        return "pos_x = " .
            $pos->x .
            ", pos_y = " .
            $pos->y .
            ", pos_z = " .
            $pos->z;
    }

    function buildExprWherePos(Position $pos)
    {
        return "pos_x = " .
            $pos->x .
            " AND pos_y = " .
            $pos->y .
            " AND pos_z = " .
            $pos->z;
    }

    function getWalls($z = null)
    {
        $where_clause = "TRUE";
        if (!is_null($z)) {
            $where_clause = "`pos_z` = " . $z;
        }
        return self::getCollectionFromDB(
            "SELECT * FROM `wall` WHERE " . $where_clause
        );
    }

    function readMap($z)
    {
        $walls = $this->getWalls($z);
        return new \BurgleBrosTwo\Utilities\Map(/*tiles=*/ [], $walls);
    }

    function updateTile($tile_id, $props)
    {
        $values = $this->buildUpdateValues($props);
        self::DbQuery(
            "UPDATE `tile` SET " .
                implode(",", $values) .
                " WHERE `id` = " .
                $tile_id
        );
    }

    function updatePc($pc_id, $props)
    {
        $values = $this->buildUpdateValues($props);
        self::DbQuery(
            "UPDATE `character_player` SET " .
                implode(",", $values) .
                " WHERE `id` = " .
                $pc_id
        );
    }

    function updateNpc($npc_id, $props)
    {
        $values = $this->buildUpdateValues($props);
        self::DbQuery(
            "UPDATE `character_npc` SET " .
                implode(",", $values) .
                " WHERE `id` = " .
                $npc_id
        );
    }

    function updateEntity($entity_id, $props)
    {
        $values = $this->buildUpdateValues($props);
        self::DbQuery(
            "UPDATE `entity` SET " .
                implode(",", $values) .
                " WHERE `id` = " .
                $entity_id
        );
    }

    private function buildUpdateValues($props)
    {
        $values = [];
        foreach ($props as $k => $v) {
            if (is_null($v)) {
                $values[] = $k . " = NULL";
            } elseif (is_int($v)) {
                $values[] = $k . " = " . $v;
            } else {
                $values[] = $k . ' = "' . $v . '"';
            }
        }
        return $values;
    }
}
