<?php

namespace BurgleBrosTwo\States;

use BurgleBrosTwo\Interfaces\World;

use BurgleBrosTwo\Models\Position;
use BurgleBrosTwo\Models\PlayerCharacter;

trait PlayerTurn
{
    function stPlayerTurn()
    {
        if ($this->getGameStateInt(GAMESTATE_INT_REMAINING_ACTIONS) <= 0) {
            $this->gamestate->nextState("tDone");
        }
    }

    function argPlayerTurn()
    {
        // XXX: Some of this is shared across all player-character
        // turn states, and should be deduplicated.

        $activeCharacter = PlayerCharacter::getActive($this);

        return [
            "actionsRemaining" => $this->getGameStateInt(
                GAMESTATE_INT_REMAINING_ACTIONS
            ),
            "activeCharacterName" =>
                CARD_DATA["bros"][$activeCharacter->bro]["title"],
            "activePlayerName" => $this->getActivePlayerName(),
            "specialActions" => $this->getSpecialPcActions(
                $this,
                $activeCharacter
            ),
        ];
    }

    // Returns actions that the player character can perform that are
    // specific to their situation, such as "while-here actions" from
    // the tile they are on.
    function getSpecialPcActions(World $world, PlayerCharacter $pc)
    {
        $special_actions = [];

        if ($pc->state == "NORMAL") {
            $tile = $world->getTileByPos($pc->pos());
            $special_actions = array_merge(
                $special_actions,
                $tile->getSpecialActions($world, $pc)
            );

            # TODO: Need to account for some token types, such as
            # escalator and monorail tokens.

            # TODO: Need to account for MOLE chips.

            # TOOD: In CtJ variant, need to offer special actions for
            # some other chip types.
        }

        return $special_actions;
    }

    // XXX: does not belong here
    //
    // Consumes $actions actions, or throws an exception if the active
    // character does not have enough actions remaining.  If the
    // character then has actions remaining, transitions to
    // $t_actions_remaining; otherwise, transitions to
    // $t_actions_exhausted.
    function consumeActionsAndTransition(
        $actions,
        $t_actions_remaining,
        $t_actions_exhausted
    ) {
        $remaining = $this->consumeActions($actions);

        if ($remaining > 0) {
            $this->gamestate->nextState($t_actions_remaining);
        } else {
            $this->gamestate->nextState($t_actions_exhausted);
        }
    }

    // XXX: does not belong here; now also used in ST_NPC_TURN.
    function consumeActions($actions)
    {
        $remaining = $this->getGameStateInt(GAMESTATE_INT_REMAINING_ACTIONS);
        if ($actions > $remaining) {
            throw new \BgaUserException("Not enough actions remaining!");
        }
        $remaining -= $actions;
        $this->setGameStateInt(GAMESTATE_INT_REMAINING_ACTIONS, $remaining);
        return $remaining;
    }

    function onActMove_stPlayerTurn(Position $pos)
    {
        // Validate that $pos is adjacent to the player's current position.
        $pc = $this->rawGetActivePlayerCharacter();
        $pcEntity = $this->rawGetEntity($pc["entity_id"]);
        $pcId = intval($pc["id"]);

        // XXX: This wouldn't be necessary if we returned a nicer
        // wrapper around $pc rather than the raw row.
        // throw new \feException('$pc: '.print_r($pc,true));
        $pcPos = Position::fromRow($pcEntity);

        $map = $this->readMap($pcEntity["pos_z"]);
        if (!$map->isAdjacent($pcPos, $pos)) {
            throw new \BgaUserException(
                'That tile is not adjacent to the character\'s current position!'
            );
        }

        $this->pushOnResolveStack([
            [
                "effectType" => "pc-leaving-tile",
                "pcId" => $pcId,
                "pos" => $pcPos->toArray(),
                "triggeringAction" => "MOVE", // XXX: not sure this is necessary
            ],
            [
                // XXX: should we instead have the 'pc-leaving-tile'
                // effect add this to the queue unless there's
                // something like a Cashier's Cage that needs to be
                // resolved first?
                "effectType" => "pc-moves",
                "pcId" => $pcId,
                "pos" => $pcPos->toArray(),
                "destPos" => $pos->toArray(),
                "triggeringAction" => "MOVE", // XXX: not sure this is necessary
            ],
        ]);

        // $this->moveEntity($pcEntity, $pos);

        // XXX: Knit this back into a library once we figure out state
        // transitions.
        //
        // $this->consumeActionsAndTransition(1, 'tContinue', 'tDone');
        $this->consumeActions(1);
        $this->gamestate->nextState("tResolveEffects");
    }

    function onActPeek_stPlayerTurn(Position $pos)
    {
        // Validate that $pos is adjacent to the player's current position.
        $pc = $this->rawGetActivePlayerCharacter();
        $pcEntity = $this->rawGetEntity($pc["entity_id"]);
        $pcId = intval($pc["id"]);

        // XXX: This wouldn't be necessary if we returned a nicer
        // wrapper around $pc rather than the raw row.
        // throw new \feException('$pc: '.print_r($pc,true));
        $pcPos = Position::fromArray($this->posFromRow($pcEntity));

        $map = $this->readMap($pcEntity["pos_z"]);
        if (!$map->isAdjacent($pcPos, $pos)) {
            throw new \BgaUserException(
                'That tile is not adjacent to the character\'s current position!'
            );
        }

        $this->pushOnResolveStack([
            [
                "effectType" => "reveal-tile",
                "pcId" => $pcId,
                "pos" => $pos->toArray(),
                "triggeringAction" => "PEEK",
            ],
        ]);

        $chips = $this->getEntitiesByPos($pos, ENTITYCLASS_CHIP);
        // XXX: RULE-QUESTION: Multiple chips on a tile don't
        // occur in the normal game; is it okay to resolve them in
        // random order?
        //
        // XXX: This is duplicated from a piece of logic in
        // ST_RESOLVE_EFFECT.
        shuffle($chips);
        foreach ($chips as $chip) {
            $this->pushOnResolveStack([
                [
                    "effectType" => "reveal-chip",
                    "pcId" => $pcId,
                    "pos" => $chip->pos->toArray(),
                    "entityId" => $chip->id,
                    "triggeringAction" => "PEEK",
                ],
                // XXX: add 'meets'?
            ]);
        }

        // $this->moveEntity($pcEntity, $pos);

        // XXX: Knit this back into a library once we figure out state
        // transitions.
        //
        // $this->consumeActionsAndTransition(1, 'tContinue', 'tDone');
        $this->consumeActions(1);
        $this->gamestate->nextState("tResolveEffects");
    }
}
