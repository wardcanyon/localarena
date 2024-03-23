<?php

namespace BurgleBrosTwo\States;

use BurgleBrosTwo\Models\Position;

trait PlaceEntranceTokens
{
    function validEntranceTokenLocations()
    {
        $existing_token_positions = array_map(function ($row) {
            return $this->posFromRow($row);
        }, $this->getEntities(/*entityType=*/ ENTITYTYPE_TOKEN_ENTRANCE));

        // Entrance tokens can be placed in the corners of Floor 1.
        $positions = [[0, 0, 0], [0, 3, 0], [3, 0, 0], [3, 3, 0]];
        return array_values(
            array_filter($positions, function ($pos) use (
                $existing_token_positions
            ) {
                return !in_array($pos, $existing_token_positions);
            })
        );
    }

    function argPlaceEntranceTokens()
    {
        return [
            "selectableTiles" => $this->validEntranceTokenLocations(),
        ];
    }

    function onActSelectTile_stPlaceEntranceTokens(Position $pos)
    {
        $entrance_token_count = count(
            self::getEntities(/*entityType=*/ ENTITYTYPE_TOKEN_ENTRANCE)
        );
        if ($entrance_token_count >= ENTRANCE_TOKEN_QTY) {
            throw new \feException("Entrance tokens have already been placed.");
        }

        // TODO: Check that position is valid.
        // TODO: Check that position does not already have an entrance token on it.
        //
        // XXX: port this to use `Position`s
        if (!in_array($pos->toArray(), $this->validEntranceTokenLocations())) {
            throw new \feException("Selected tile is not valid.");
        }

        self::createEntity(
            ENTITYTYPE_TOKEN_ENTRANCE,
            $pos,
            "An entrance token was placed."
        );
        ++$entrance_token_count;

        // If we have placed enough tokens, move to the next state;
        // otherwise, return to ST_PLACE_ENTRANCE_TOKENS to place
        // another token.

        if ($entrance_token_count >= ENTRANCE_TOKEN_QTY) {
            self::trace(
                "onActSelectTile_stPlaceEntranceTokens(): tNextCharacter"
            );
            $this->gamestate->nextState("tNextCharacter");
        } else {
            self::trace("onActSelectTile_stPlaceEntranceTokens(): tContinue");
            $this->gamestate->nextState("tContinue");
        }
    }
}
