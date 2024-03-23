<?php

namespace BurgleBrosTwo\States;

trait PlayerTurnEnds
{
    function stPlayerTurnEnds()
    {
        $this->addNpcTurns();

        $this->gamestate->nextState("tNextCharacter");
    }

    function addNpcTurns()
    {
        // XXX: There's a lot of boilerplate here that I bet we wind
        // up repeating a lot.
        $pc = $this->rawGetActivePlayerCharacter();
        if (is_null($pc)) {
            throw new \feException(
                "Oops: rawGetActivePlayerCharacter() returned null!"
            );
        }
        $pcEntity = $this->rawGetEntity($pc["entity_id"]);
        $pcPos = $this->posFromRow($pcEntity);

        // Give bouncer(s) turns.
        //
        // XXX: Also need to handle other NPCs such as TIGERs.
        foreach (
            $this->getEntities(ENTITYTYPE_CHARACTER_BOUNCER)
            as $npcEntity
        ) {
            $npc = $this->rawGetNpcByEntityId($npcEntity["id"]);
            $npcPos = $this->posFromRow($npcEntity);

            if ($npc["npc_type"] == "BOUNCER") {
                // $npcEntity = $this->rawGetEntity($npc['entity_id']);

                if ($npcPos[2] == $pcPos[2]) {
                    // Same floor!  This bouncer gets a turn.
                    $this->pushOnTurnStack([
                        "character_type" => "NPC",
                        "character_id" => $npc["id"],
                    ]);
                }
            }
        }
    }

    // XXX: does not belong here
    function pushOnTurnStack($entry)
    {
        $turn_stack = $this->getGameStateJson(GAMESTATE_JSON_TURN_STACK);
        $turn_stack[] = $entry;
        $this->setGameStateJson(GAMESTATE_JSON_TURN_STACK, $turn_stack);
    }

    // Returns the next entry in the turn stack, or null if it is empty.
    function popFromTurnStack()
    {
        $turn_stack = $this->getGameStateJson(GAMESTATE_JSON_TURN_STACK);
        if (count($turn_stack) > 0) {
            $entry = array_pop($turn_stack);
            $this->setGameStateJson(GAMESTATE_JSON_TURN_STACK, $turn_stack);
            return $entry;
        }
        return null;
    }
}
