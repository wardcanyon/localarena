<?php

namespace BurgleBrosTwo;

//
// Utilities related to my custom, character-based (not player-based)
// turn order system
//
// TODO: Write better docs.
//
// References about related BGA functionality:
//
// - "Custom players turn order": https://forum.boardgamearena.com/viewtopic.php?t=10273
//

trait TurnOrder
{
    // Randomly assign a unique turn order to each character.
    //
    // Called in the ST_FINISH_SETUP state, after the list of
    // player-characters is finalized.
    function finishSetupTurnOrder()
    {
        $playerCharacters = $this->rawGetPlayerCharacters();

        // TODO: Probably need to support some other
        // (game-option-driven) ways of choosing player order, but
        // random will be fine for now.
        shuffle($playerCharacters);

        $i = 0; // XXX: Should be able to replace this with `foreach()` syntax.
        foreach ($playerCharacters as $playerCharacter) {
            self::DbQuery(
                "UPDATE `character_player` SET turn_order=" .
                    $i .
                    " WHERE `id` = " .
                    $playerCharacter["id"]
            );
            ++$i;
        }

        // Initialize gamestate related to turn order.
        $this->setGameStateJson(GAMESTATE_JSON_ACTIVE_CHARACTER, []);
        $this->setGameStateJson(GAMESTATE_JSON_TURN_STACK, []);
        $firstPlayerCharacter = $this->getNextPlayerCharacterInTurnOrder(-1);
        $this->setGameStateInt(
            GAMESTATE_INT_NEXT_PLAYER_CHARACTER,
            intval($firstPlayerCharacter["id"])
        );

        // XXX: We need to send something to the client so
        // that the humans can see the turn-order.
    }

    function activatePc($pcId)
    {
        $nextPlayerCharacter = $this->rawGetPlayerCharacter($pcId);

        // Set the corresponding character and their BGA player active.
        $this->setGameStateJson(GAMESTATE_JSON_ACTIVE_CHARACTER, [
            "character_type" => "PLAYER",
            "character_id" => intval($pcId),
        ]);

        $this->setGameStateInt(
            GAMESTATE_INT_REMAINING_ACTIONS,
            PC_ACTIONS_PER_TURN
        );

        // N.B.: We have to change the active player *before*
        // transitioning to another active-player state, or we'll get
        // an error ("Impossible to change active player during
        // activeplayer type state"); ref.:
        // https://forum.boardgamearena.com/viewtopic.php?t=22654.
        $this->gamestate->changeActivePlayer($nextPlayerCharacter["player_id"]);
        // XXX: We could also get rid of the "NOT_ENTERED" state and
        // just look at whether the entity is on the board or not.
        if ($nextPlayerCharacter["state"] == "NOT_ENTERED") {
            // This should happen only on the first turn that this
            // player-character takes.
            self::trace("nextCharacterTurn(): tPlayerCharacterTurnEnterMap");
            $this->gamestate->nextState("tPlayerCharacterTurnEnterMap");
        } else {
            self::trace("nextCharacterTurn(): tPlayerCharacterTurn");
            $this->gamestate->nextState("tPlayerCharacterTurn");
        }
    }

    function activateNpc($npcId)
    {
        $this->setGameStateInt(
            GAMESTATE_INT_REMAINING_ACTIONS,
            NPC_ACTIONS_PER_TURN
        );
        $this->setGameStateJson(GAMESTATE_JSON_ACTIVE_CHARACTER, [
            "character_type" => "NPC",
            "character_id" => intval($npcId),
        ]);

        $this->gamestate->nextState("tNpcTurn");
    }

    // XXX: This is called in the ST_NEXT_CHARACTER state.  In general
    // you should transition into that state rather than trying to
    // call this function directly.
    //
    // This function activates a particular player and then possibly
    // transitions into an activeplayer state, and that can't be done
    // if you are already in another activeplayer state.
    function nextCharacterTurn()
    {
        $turn_entry = $this->popFromTurnStack();
        if (!is_null($turn_entry)) {
            switch ($turn_entry["character_type"]) {
                case "PLAYER":
                    return $this->activatePc(
                        intval($turn_entry["character_id"])
                    );
                case "NPC":
                    return $this->activateNpc(
                        intval($turn_entry["character_id"])
                    );
                default:
                    throw new \feException("Invalid entry in turn stack.");
            }
        }

        // Figure out who's next in the player-character turn order,
        // and who the subsequent ("next-next") player-character will
        // be.
        $nextPlayerCharacterId = $this->getGameStateInt(
            GAMESTATE_INT_NEXT_PLAYER_CHARACTER
        );
        $nextPlayerCharacter = $this->rawGetPlayerCharacter(
            $nextPlayerCharacterId
        );
        $subsequentPlayerCharacter = $this->getNextPlayerCharacterInTurnOrder(
            $nextPlayerCharacter["turn_order"]
        );

        // XXX: The `intval()` call here is necessary because the BGA
        // framework returns eveything as a string.  We should improve
        // our `getPlayerCharacter*()` and other DB helpers, and then
        // remove this.
        $this->setGameStateInt(
            GAMESTATE_INT_NEXT_PLAYER_CHARACTER,
            intval($subsequentPlayerCharacter["id"])
        );

        $this->activatePc($nextPlayerCharacterId);
    }

    // Returns the the player-character who goes next in the turn
    // order after `$turnOrder`.
    //
    // N.B.: This assumes that `turn_order` values have been uniquely
    // assigned.
    function getNextPlayerCharacterInTurnOrder($turnOrder)
    {
        $playerCharacter = self::getObjectFromDB(
            "SELECT * FROM `character_player` WHERE `turn_order` > " .
                $turnOrder .
                " ORDER BY `turn_order` ASC LIMIT 1"
        );
        if (is_null($playerCharacter)) {
            // There's nobody after that point in the turn order;
            // start over with the character that has the lowest
            // turn-order value.
            $playerCharacter = self::getObjectFromDB(
                "SELECT * FROM `character_player` ORDER BY `turn_order` ASC LIMIT 1"
            );
        }
        return $playerCharacter;
    }

    // Throws an exception if the character identified by
    // `$characterId` is not controlled by the current player (the one
    // sending the request).
    function checkCurrentPlayerControlCharacter($characterId)
    {
        // XXX: TODO: ...
    }
}
