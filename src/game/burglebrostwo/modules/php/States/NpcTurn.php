<?php

namespace BurgleBrosTwo\States;

use BurgleBrosTwo\Models\Npc;
use BurgleBrosTwo\Models\Entity;
use BurgleBrosTwo\Models\Position;

trait NpcTurn
{
    // XXX: does not belong here
    function getActiveNpc()
    {
        $active_character = $this->getGameStateJson(
            GAMESTATE_JSON_ACTIVE_CHARACTER
        );
        if ($active_character["character_type"] != "NPC") {
            throw new \feException("Active character is not an NPC!");
        }
        return Npc::getById($this, $active_character["character_id"]);
    }

    function stNpcTurn()
    {
        $npc = $this->getActiveNpc();

        $actions_remaining = $this->getGameStateInt(
            GAMESTATE_INT_REMAINING_ACTIONS
        );
        if ($actions_remaining <= 0) {
            // XXX: This is mostly a temporary thing for development
            // purposes, so that we can see that the NPC "takes their
            // turn" before we have that implemented.
            self::notifyAllPlayers(
                "npcTurnEnds",
                clienttranslate("NPC takes their turn."),
                [
                    // XXX: include NPC name?
                ]
            );

            $this->gamestate->nextState("tDone");
            return;
        }

        // XXX: We should be able to avoid repeated map reads
        // through memoization.
        $map = $this->readMap($npc->entity->pos->z);
        $path = $map->shortestPathClockwise(
            $npc->entity->pos,
            $npc->destination_entity->pos
        );

        if (count($path) < 1) {
            throw new \feException(
                "Oops!  It should not be possible for the bouncer to have a turn where he has already reached his destination."
            );
        }

        $nextPos = $path[0];
        $this->pushOnResolveStack([
            [
                "effectType" => "npc-moves",
                "npcId" => $npc->id,
                "pos" => $npc->pos()->toArray(),
                "destPos" => $nextPos->toArray(),
            ],
        ]);
        $this->consumeActions(1);

        if (count($path) <= 1) {
            // Draw a new destination for the bouncer.

            $patrolDeck = new \BurgleBrosTwo\Managers\CardManager(
                "PATROL",
                $npc->pos()->z
            );
            $patrolCard = $patrolDeck->drawAndDiscard();
            if (is_null($patrolCard)) {
                throw new \feException("Hunting behavior not implemented!");
            }

            // XXX: BUG: Need to handle "distracted" cards; when we draw
            // one of those, we need to pause for player input and ask
            // the player(s) to choose an even-numbered tile.  (What
            // if there is no even-numbered tile?)

            $destinationPos = $this->posFromPatrolCard($patrolCard); // XXX: this should return a Position
            $this->jumpEntity(
                $npc->destination_entity,
                Position::fromArray($destinationPos)
            );
            // XXX: Wrap this up into a library function that also sends notifs, etc.?
            self::notifyAllPlayers(
                "npcDestinationChanges",
                clienttranslate("NPC chooses a new destination."),
                [
                    // XXX: include NPC name?
                ]
            );
        }

        $this->gamestate->nextState("tResolveEffect");
    }
}
