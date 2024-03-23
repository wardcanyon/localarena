<?php

namespace BurgleBrosTwo;

use BurgleBrosTwo\Models\Npc;
use BurgleBrosTwo\Models\PlayerCharacter;
use BurgleBrosTwo\Models\Position;
use BurgleBrosTwo\Models\EffectContext;

trait GameEffects
{
    // The given $effects are added to the front of the resolve stack,
    // in the order in which they are specified.
    function pushOnResolveStack($effects)
    {
        foreach ($effects as $effect) {
            if (!is_array($effect)) {
                throw new \feException(
                    "Internal error: each element pushed onto the resolve stack must be an array describing an effect."
                );
            }
        }

        $resolve_stack = $this->getGameStateJson(GAMESTATE_JSON_RESOLVE_STACK);
        $resolve_stack = array_merge($effects, $resolve_stack);
        $this->setGameStateJson(GAMESTATE_JSON_RESOLVE_STACK, $resolve_stack);
    }

    function popFromResolveStack()
    {
        $resolve_stack = $this->getGameStateJson(GAMESTATE_JSON_RESOLVE_STACK);
        if (count($resolve_stack) > 0) {
            $entry = array_shift($resolve_stack);
            $this->setGameStateJson(
                GAMESTATE_JSON_RESOLVE_STACK,
                $resolve_stack
            );
            return $entry;
        }
        return null;
    }

    function pushOnResolveValueStack($value)
    {
        if (!is_array($value)) {
            throw new \feException(
                "Internal error: each element pushed onto the resolve-value stack must be an array describing a resolve-value."
            );
        }

        // XXX: Validate 'valueType', 'productionDepth'.

        $resolve_value_stack = $this->getGameStateJson(
            GAMESTATE_JSON_RESOLVE_VALUE_STACK
        );
        $resolve_value_stack[] = $value;
        $this->setGameStateJson(
            GAMESTATE_JSON_RESOLVE_VALUE_STACK,
            $resolve_value_stack
        );
    }

    function peekFromResolveValueStack()
    {
        $resolve_value_stack = $this->getGameStateJson(
            GAMESTATE_JSON_RESOLVE_VALUE_STACK
        );
        if (count($resolve_value_stack) > 0) {
            return $resolve_value_stack[0];
        }
        return null;
    }

    function popFromResolveValueStack()
    {
        $resolve_value_stack = $this->getGameStateJson(
            GAMESTATE_JSON_RESOLVE_VALUE_STACK
        );
        if (count($resolve_value_stack) > 0) {
            $entry = array_shift($resolve_value_stack);
            $this->setGameStateJson(
                GAMESTATE_JSON_RESOLVE_VALUE_STACK,
                $resolve_value_stack
            );
            return $entry;
        }
        return null;
    }

    // XXX: There are a few things, like `pcId` (which should maybe be
    // `triggeringPcId`) and `triggeringAction`, which should be
    // passed along to effects created while resolving another effect
    // (by default).
    function resolveEffects()
    {
        // XXX:
        $world = $this;

        // Pop the top thing off the stack.  If the stack is empty,
        // return to whichever state is appropriate to continue the
        // active character's turn.
        $effect = $world->popFromResolveStack();
        if (is_null($effect)) {
            $active_character = $world->getGameStateJson(
                GAMESTATE_JSON_ACTIVE_CHARACTER
            );
            switch ($active_character["character_type"]) {
                case "PLAYER":
                    $world->nextState("tContinuePcTurn");
                    break;
                case "NPC":
                    $world->nextState("tContinueNpcTurn");
                    break;
                default:
                    throw new \feException(
                        "Unexpected character_type for active character."
                    );
            }
            return;
        }

        $ctx = new EffectContext();
        $ctx->triggeringAction = $effect["triggeringAction"] ?? null;
        $ctx->stackDepth =
            count($world->getGameStateJson(GAMESTATE_JSON_RESOLVE_STACK)) + 1; // XXX: Duplicate read.
        $ctx->rawEffect = $effect;
        if (array_key_exists("pcId", $effect)) {
            $ctx->pc = PlayerCharacter::getById($this, $effect["pcId"]);
        }
        if (array_key_exists("npcId", $effect)) {
            $ctx->npc = Npc::getById($this, $effect["npcId"]);
        }
        if (array_key_exists("pos", $effect)) {
            $ctx->pos = Position::fromArray($effect["pos"]);
        }

        $actionWindow = function () use ($world, $effect) {
            $effect["actWindowDone"] = true;
            $world->pushOnResolveStack([$effect]);
            $world->nextState("tActionWindow");
        };

        $world->notifyDebug(
            "ResolveEffect",
            'Resolving effect "' .
                $effect["effectType"] .
                '": ' .
                print_r($effect, true)
        );

        switch ($effect["effectType"]) {
            case "pc-leaving-tile":
                // At this stage, the move has not happened yet, and may
                // be canceled.  This effect is followed by a "pc-moves"
                // effect.

                // N.B.: Cashier's Cages requires some logic here; but for
                // most tile types, this really is just a no-op.
                $tile = $world->getTileByPos($ctx->pos);
                $tile->onPcLeaving($world, $ctx);
                break;
            case "pc-moves":
                // Resolving this effect is what actually moves the player
                // character.  At this point, any effects related to
                // leaving the old location have been resolved.
                //
                // Afterwards, the "pc-entering-tile" effect is applied.

                // XXX: Ensure that the PC is still at $effect['pos'] and
                //   that a move to $effect['destPos'] is still valid; if
                //   not, something has gone wrong.
                //
                // XXX: This should work on an `Entity` and not a raw entity row.
                $world->moveEntity(
                    $ctx->pc->entity,
                    Position::fromArray($effect["destPos"])
                );

                $world->pushOnResolveStack([
                    [
                        "effectType" => "pc-entering-tile",
                        "pcId" => $effect["pcId"],
                        "pos" => $effect["destPos"],
                        "srcPos" => $effect["pos"],
                        "triggeringAction" => "MOVE",
                    ],
                ]);

                break;
            case "reveal-chip":
                // $effect['triggeringAction'] (with values like "MOVE",
                // "PEEK", "GEAR", etc.) describes the proximate player
                // action that triggered the effect.  Many chips do
                // different things if the value is "MOVE" or "PEEK".

                // If the chip with $effect['entityId'] is not already
                // revealed, mark it as revealed and send a notif to all
                // clients that it has been revealed.
                $chip = $world->getEntity($effect["entityId"]);
                if ($chip->state == "HIDDEN") {
                    $world->revealChip($chip);
                }

                // Call the type-specific logic for this chip.
                $chip->onReveal($world, $ctx);
                break;
            case "reveal-tile":
                // throw new \feException('reveal-tile reached');
                // throw new \feException(print_r($effect['pos']), true);

                // If the tile at $effect['pos'] is already revealed, this
                // is a no-op; otherwise, mark it as revealed and send a
                // notif to all clients that it has been revealed.
                $tile = $world->getTileByPos(
                    Position::fromArray($effect["pos"])
                );
                if ($tile->state == "HIDDEN") {
                    $world->revealTile($tile);
                }
                break;
            case "pc-entering-tile":
                // N.B.: `srcPos` may be null in certain special cases,
                // such as when the character is entering the map for the
                // first time.

                // XXX: should we call this 'pc-entering-tile' and have
                // 'pc-enters-tile' be the thing that applies when-enters
                // effects after NPC/chip effects are applied?

                // If the player is moving, this happens before any of the
                // "*-revealed" effects; this is where the player gets
                // heat.

                // TODO: Check for other entities; in particular, add heat
                // if a bouncer is here.  (That also needs to check for
                // and possibly consume a crowd token or chip, though; the
                // "pc-meets-npc" effect should handle that.)

                // Apply any meeting effects if there are NPCs on this tile.
                foreach (
                    $world->getEntitiesByPos($ctx->pos, ENTITYCLASS_CHARACTER)
                    as $c
                ) {
                    if ($c::ENTITY_TYPE != ENTITYTYPE_CHARACTER_PLAYER) {
                        $npcRaw = $world->rawGetNpcByEntityId($c->id);
                        $world->pushOnResolveStack([
                            [
                                "effectType" => "pc-meets-npc",
                                "pcId" => $effect["pcId"],
                                "npcId" => intval($npcRaw["id"]), // XXX: replace with typed Npc class
                                "pos" => $ctx->pos->toArray(),
                            ],
                        ]);
                    }
                }

                // // XXX: The resolve stack -- for each unrevealed chip,
                // // reveal it and then apply any "pc-meets-chip".  If the
                // // tile is unrevealed, reveal it.  Apply any when-enters
                // // effects from the tile.
                $world->pushOnResolveStack([
                    [
                        "effectType" => "reveal-tile",
                        "pcId" => $effect["pcId"],
                        "pos" => $effect["pos"],
                    ],
                    [
                        "effectType" => "pc-enters-tile",
                        "pcId" => $effect["pcId"],
                        "pos" => $effect["pos"],
                    ],
                ]);

                $chips = $world->getEntitiesByPos($ctx->pos, ENTITYCLASS_CHIP);
                // XXX: RULE-QUESTION: Multiple chips on a tile don't
                // occur in the normal game; is it okay to resolve them in
                // random order?
                shuffle($chips);
                foreach ($chips as $chip) {
                    $world->pushOnResolveStack([
                        [
                            "effectType" => "reveal-chip",
                            "pcId" => $effect["pcId"],
                            "pos" => $chip->pos->toArray(),
                            "entityId" => $chip->id,
                            "triggeringAction" => "MOVE",
                        ],
                        // XXX: add 'meets'?
                    ]);
                }

                break;
            case "pc-enters-tile":
                // At this point, the PC has finally entered the tile; the
                // tile is definitely revealed, and any NPC or chip
                // meeting effects have been resolved.

                // This is where any "when-enters" effects are applied.

                $tile = $world->getTileByPos($ctx->pos);
                $tile->onPcEnters($world, $ctx);
                break;
            case "pc-meets-npc":
                // This effect is resolved after an NPC and a PC wind up
                // in the same position (e.g. because one of them moved or
                // jumped).
                //
                // XXX: Should `onMeetsPc()` be a member of `Npc` rather
                // than `NpcEntity`?
                $ctx->npc->entity->onMeetsPc($this, $ctx);
                break;
            case "pc-meets-chip":
                // This effect is resolved after an NPC and a PC wind up
                // in the same position (e.g. because one of them moved or
                // jumped).

                // When this effect is resolved, it is safe to assume that
                // the chip has been revealed.

                $chip = $world->getEntity($effect["entityId"]);
                if ($chip->state == "HIDDEN") {
                    $world->revealChip($chip);
                }

                // Call the type-specific logic for this chip.
                $chip->onMeetsPc($world, $ctx);
                break;
            case "npc-moves":
                if (!($effect["actWindowDone"] ?? false)) {
                    return $actionWindow();
                }

                // XXX: Ensure that the NPC is still at $effect['pos'] and
                //   that a move to $effect['destPos'] is still valid; if
                //   not, something has gone wrong.
                //
                // XXX: This should work on an `Entity` and not a raw entity row.
                $world->moveEntity(
                    $ctx->npc->entity,
                    Position::fromArray($effect["destPos"])
                );
                $world->pushOnResolveStack([
                    [
                        "effectType" => "npc-entering-tile",
                        "npcId" => $effect["npcId"],
                        "pos" => $effect["destPos"],
                        "srcPos" => $effect["pos"],
                        "triggeringAction" => "MOVE",
                    ],
                ]);
                break;
            case "npc-entering-tile":
                // This is where any "*-meets-npc" effects are applied; an
                // "npc-enters-tile" effect is also applied.

                // XXX: Check that the NPC is still here; if not,
                // something has gone wrong!

                // Apply any meeting effects if there are PCs on this tile.
                foreach (
                    $world->getEntitiesByPos($ctx->pos, ENTITYCLASS_CHARACTER)
                    as $c
                ) {
                    if ($c::ENTITY_TYPE == ENTITYTYPE_CHARACTER_PLAYER) {
                        $pcRaw = $world->rawGetPlayerCharacterByEntityId($c->id);
                        $world->pushOnResolveStack([
                            [
                                "effectType" => "pc-meets-npc",
                                "npcId" => $ctx->npc->id,
                                "pcId" => intval($pcRaw["id"]), // XXX: replace with typed PlayerCharacter class
                                "pos" => $ctx->pos->toArray(),
                            ],
                        ]);
                    }
                }

                // Push this last so that it's resolved before any meeting
                // effects are.
                $world->pushOnResolveStack([
                    [
                        "effectType" => "npc-enters-tile",
                        "npcId" => $effect["npcId"],
                        "pos" => $effect["pos"],
                    ],
                ]);

                break;
            case "npc-enters-tile":
                // At this point, the NPC has finally entered the tile.
                // Any NPC/PC meeting effects have been resolved.

                // This is where any "when-bouncer-enters" effects are applied.

                $tile = $world->getTileByPos($ctx->pos);
                $tile->onNpcEnters($world, $ctx);
                break;
            case "draw-event-card":
                // In this state, we draw a card and reveal it to the
                // players.  Then we continue to resolve the
                // "resolve-event-card" effect, where we offer an action
                // window before resolving the effect of the event card.

                // This should be one of "POOL" or "LOUNGE".
                $event_deck = new \BurgleBrosTwo\Managers\CardManager(
                    $effect["eventDeck"]
                );
                $card = $event_deck->drawAndDiscard();
                $card_data =
                    CARD_DATA[$card["card_type_group"]][$card["card_type"]];

                $world->notifyAllPlayers(
                    "eventCardDrawn",
                    'An event card is drawn! It is: ${cardTitle}',
                    [
                        "cardTitle" => $card_data["title"],
                    ]
                );

                $world->pushOnResolveStack([
                    array_merge($effect, [
                        "effectType" => "resolve-event-card",
                        "eventCardId" => intval($card["id"]),
                    ]),
                ]);

                break;
            case "resolve-event-card":
                if (!($effect["actWindowDone"] ?? false)) {
                    return $actionWindow();
                }

                $event_deck = new \BurgleBrosTwo\Managers\CardManager(
                    $effect["eventDeck"]
                );
                $card = $event_deck->get($effect["eventCardId"]);
                $card_data =
                    CARD_DATA[$card["card_type_group"]][$card["card_type"]];

                $world->notifyAllPlayers(
                    "eventCardDrawn",
                    'An event card is resolved! It is: ${cardTitle}',
                    [
                        "cardTitle" => $card_data["title"],
                    ]
                );
                // XXX: need to actually resolve it

                break;
            case "roll-dice":
                // throw new \feException('no impl: roll-dice');

                // In this state, we generate a new roll of the given
                // number of dice and put it on the value stack.
                //
                // Then, we push a 'resolve-dice' effect onto the stack;
                // it handles the action window where players can react to
                // the result of the roll before it is finalized.

                $dice = [];
                for ($i = 0; $i < $effect["diceQty"]; $i++) {
                    $dice[] = rand(1, 6);
                }

                $world->pushOnResolveValueStack([
                    "valueType" => "dice",
                    "productionDepth" => $ctx->stackDepth,
                    "dice" => $dice,
                ]);
                $world->pushOnResolveStack([
                    [
                        "effectType" => "resolve-dice",
                        // XXX: do we need pcId/npcId/pos/etc. here?
                    ],
                ]);

                $world->notifyAllPlayers(
                    "diceRolled",
                    'Rolling ${diceQty}d6!  The result is: ${diceStr}',
                    [
                        "diceQty" => count($dice),
                        "diceStr" => implode(", ", $dice),
                    ]
                );

                break;
            case "resolve-dice":
                // XXX: Eventually, we need to tell $actionWindow that
                // this is specifically a react-to-dice-roll window and
                // not a general action window, since there are some
                // actions that are only valid in response to a dice roll.
                if (!($effect["actWindowDone"] ?? false)) {
                    return $actionWindow();
                }

                // N.B.: As soon as the action window ends, we resolve the
                // dice and return the result to the thing below us on the
                // effect stack.
                break;
            case "commotion":
                // XXX: Eventually, we need to tell $actionWindow that
                // (only the first of these) is specifically a
                // react-to-commotion window and not a general action
                // window, since there are some actions that are only
                // valid in response to a dice roll, such as one of the
                // Hacker's gear cards.
                if (!($effect["actWindowDone"] ?? false)) {
                    return $actionWindow();
                }

            // Move bouncer destination to the effect's position.

            // XXX: How do we manage repeating this until the
            // bouncer's movement is exhausted?  Do we just push that
            // many npc-moves effects on the stack, and then cancel
            // some of them based on things like the
            // `StopAtDestinationStatus`?

            // XXX: We probably need the ability to store statuses and
            // to write test cases to get this reasonably correct.
            default:
                throw new \feException(
                    "Unexpected effect type on resolve stack: " .
                        $effect["effectType"]
                );
        }

        $world->notifyDebug(
            "ResolveEffect",
            "Current resolve stack: " .
                print_r(
                    $world->getGameStateJson(GAMESTATE_JSON_RESOLVE_STACK, true),
                    true
                )
        );

        // Continue on to process the next item in the stack.
        $world->nextState("tNextEffect");
    }
}

class GameEffectsClass
{
    use \BurgleBrosTwo\GameEffects;
}
