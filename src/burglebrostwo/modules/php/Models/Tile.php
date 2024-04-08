<?php

namespace BurgleBrosTwo\Models;

use BurgleBrosTwo\Interfaces\World;

use BurgleBrosTwo\Models\EffectContext;
use BurgleBrosTwo\Models\PlayerCharacter;

abstract class Tile
{
    public int $id;
    public Position $pos;
    public string $state;
    public int $tile_number;
    public int $counting_cubes;

    // By default, these are no-ops; tile classes can override
    // whichever they need to.
    public function onPcEnters(World $world, EffectContext $ctx)
    {
    }
    public function onPcLeaving(World $world, EffectContext $ctx)
    {
    }
    public function onNpcEnters(World $world, EffectContext $ctx)
    {
    }

    // Returns a list of special action descriptions (the Typescript
    // `SpecialActionInfo` type) that the player character can perform
    // while on this tile.
    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        // By default, there are no special actions associated with a
        // tile.  Tiles should override this in order to inform the
        // client about things like "while-here" actions.
        return [];
    }

    // XXX: Rename this "onSpecialAction"?
    public function onWhileHereAction(World $world, $actionParams)
    {
        throw new \feException(
            'No "while here" action is defined for this tile type!'
        );
    }
}

trait TileManager
{
    public function getTileByPos(Position $pos): Tile
    {
        return $this->tileFromRow($this->rawGetTileByPos($pos));
    }

    public function getTileById(int $tile_id): Tile
    {
        return $this->tileFromRow($this->rawGetTile($tile_id));
    }

    private function tileFromRow($row): Tile
    {
        // XXX: We should only need to do this once, and then we
        // should be able to cache it.
        $classByType = [];
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, "BurgleBrosTwo\Models\Tile")) {
                $rc = new \ReflectionClass($class);
                if (!$rc->isAbstract()) {
                    $classByType[$class::TILE_TYPE] = $rc;
                }
            }
        }

        $ret = $classByType[$row["tile_type"]]->newInstance();
        $ret->id = intval($row["id"]);
        $ret->counting_cubes = intval($row["counting_cubes"]);
        $ret->tile_number = intval($row["tile_number"]);
        $ret->pos = Position::fromRow($row);
        $ret->state = $row["state"];
        return $ret;
    }
}

// This is a tile type that has no special behavior at all; it's used
// only for testing.
class NoopTile extends Tile
{
    const TILE_TYPE = TILETYPE_NOOP;
}

// This is the logic shared between the "pool" and "lounge" tiles
// (both of which make you draw an event card from their respective
// decks).
abstract class EventTile extends Tile
{
    public function onPcEnters(World $world, EffectContext $ctx)
    {
        // XXX: Push onto resolve stack: draw a card from EVENT_DECK
        //   and resolve it.

        // XXX: Replace this with a typed wrapper that also passes
        // along all of the appropriate contextual info.
        $world->pushOnResolveStack([
            [
                "effectType" => "draw-event-card",
                "eventDeck" => $this::EVENT_DECK,
                "pos" => $ctx->pos->toArray(),
                "pcId" => $ctx->pc->id,
            ],
        ]);
    }
}

class PoolTile extends EventTile
{
    const TILE_TYPE = TILETYPE_POOL;
    const EVENT_DECK = "POOL";
}

class LoungeTile extends EventTile
{
    const TILE_TYPE = TILETYPE_LOUNGE;
    const EVENT_DECK = "LOUNGE";
}

// XXX: Incomplete; requires some UI work.
class TableGamesTile extends Tile
{
    const TILE_TYPE = TILETYPE_TABLE_GAMES;

    # whenYouEnter: "roll 2 dice; on 7 or 11, lose 1 heat; otherwise, make commotion"
}

class FrontDeskTile extends Tile
{
    const TILE_TYPE = TILETYPE_FRONT_DESK;

    public function onPcEnters(World $world, EffectContext $ctx)
    {
        # whenYouEnter: "if you have less than 3 heat, gain 1 heat"
        if ($ctx->pc->heat < 3) {
            $world->addHeat($ctx->pc, 1);
        }
    }
}

class SurveillanceTile extends Tile
{
    const TILE_TYPE = TILETYPE_SURVEILLANCE;

    public function onNpcEnters(World $world, EffectContext $ctx)
    {
        if ($ctx->npc->getNpcType() == "BOUNCER") {
            $tile = $world->getTileByPos($ctx->pos);
            $world->addCountingCubes($tile, 1);
            if ($world->getCountingCubes($tile) >= 4) {
                $world->setCountingCubes($tile, 0);

                // Each player on this floor gains 2 heat.
                foreach ($world->getPlayerCharacters() as $pc) {
                    if ($pc->pos[2] == $ctx->pos[2]) {
                        $world->addHeat($pc, 2);
                    }
                }
            }
        }
    }

    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        # whileHereAction: you may spend an action to clear all cubes here
        return [
            [
                "title" => "Clear cubes",
                "name" => "clearCubes",
                "action" => "actWhileHere",
                "params" => [],
            ],
        ];
    }
}

// XXX: no World API for adding statuses to the bouncer yet
class BuffetTile extends Tile
{
    const TILE_TYPE = TILETYPE_BUFFET;

    public function onPcEnters(World $world, EffectContext $ctx)
    {
        # whenYouEnter: add 1 cube
        # cubeLimit: 3
        # cubeEffect: clear cubes; commotion, but bouncer moves 3 spaces (not 1) and stops here

        # XXX: the "stops here" thing will need some special logic
        # probably -- maybe a special status that only has a brief
        # lifetime and tells the bouncer to stop if they reach $ctx->pos?
        #
        # (this is sketched as `StopAtDestinationStatus`)

        $tile = $world->getTileByPos($ctx->pos);
        $world->addCountingCubes($tile, 1);
        if ($world->getCountingCubes($tile) >= 3) {
            $world->setCountingCubes($tile, 0);
            $world->triggerCommotion($ctx->pos, /*bouncerMovement=*/ 3);
        }
    }
}

class PitBossTile extends Tile
{
    const TILE_TYPE = TILETYPE_PIT_BOSS;

    public function onPcEnters(World $world, EffectContext $ctx)
    {
        if ($ctx->pc->heat < 4) {
            $world->triggerCommotion($ctx->pos);
        }
    }
}

// XXX: whileHereStatus
// XXX: ifYouEndYourTurnHere
class CountRoomTile extends Tile
{
    const TILE_TYPE = TILETYPE_COUNT_ROOM;

    # whileHereStatus: you cannot peek
    #   - if this is the only "whileHereStatus" effect, maybe this isn't
    #     worth doing using the status system?  (maybe we just hard-code it?)
    # ifYouEndYourTurnHere: commotion
}

class SlotsTile extends Tile
{
    const TILE_TYPE = TILETYPE_SLOTS;

    # whenYouEnter: make a commotion unless a teammate is in the same row or column
    public function onPcEnters(World $world, EffectContext $ctx)
    {
        $teammate = false;
        foreach ($world->getPlayerCharacters() as $pc) {
            if ($pc->id != $ctx->pc->id) {
                if (
                    !(
                        $pc->pos[0] == $ctx->pos[0] ||
                        $pc->pos[1] == $ctx->pos[1]
                    )
                ) {
                    $teammate = true;
                }
            }
        }

        if (!$teammate) {
            $world->triggerCommotion($ctx->pos);
        }
    }
}

// XXX: when-leaving dice roll
class CashierCagesTile extends Tile
{
    const TILE_TYPE = TILETYPE_CASHIER_CAGES;

    public function onPcEnters(World $world, EffectContext $ctx)
    {
        $tile = $world->getTileByPos($ctx->pos);
        if ($world->getCountingCubes($tile) < 5) {
            $world->addCountingCubes($tile, 1);
        }
    }

    public function onPcLeaving(World $world, EffectContext $ctx)
    {
        // XXX: Some triggeringActions should probably be ignored
        // here; e.g. if we get jumped by a card effect, that doesn't
        // trigger this, does it?

        // XXX: Most of this could probably be wrapped up in an "I
        //   want a roll of Xd6" wrapper.

        $value = $world->peekFromResolveValueStack();
        if (
            $value == null ||
            $value["valueType"] != "dice" ||
            $value["productionDepth"] <= $ctx->stackDepth
        ) {
            // If we don't have a die result that looks like it's for us:
            // - push this effect back on the stack
            // - push a 'dice-roll' on the stack

            $world->pushOnResolveStack([
                [
                    "effectType" => "roll-dice",
                    "diceQty" => 1,
                ],
                $ctx->rawEffect,
            ]);

            return;
        }

        $world->popFromResolveValueStack();
        $dice = $value["dice"];
        if (count($dice) != 1) {
            throw new \feException(
                "Internal error: unexpected number of result dice."
            );
        }

        // XXX: How should we expose this to code here?  Add it to $world?
        //
        // // XXX: For now, all we do is notify the players of the
        // // final/resolved dice values.
        // $this->notifyAllPlayers('diceResolved', 'Resolving ${diceQty}d6!  The result is: ${diceStr}', [
        //     'diceQty' => count($dice),
        //     'diceStr' => implode(', ', $dice),
        // ]);

        $tile = $world->getTileByPos($ctx->pos);
        if ($dice[0] < $world->getCountingCubes($tile)) {
            // XXX: TODO:
            // Cancel move.
        }
    }
    # TODO:
    # whenLeaving: roll a die; stay if roll is less than # cubes
}

function adjacentIgnoringWalls(Position $a, Position $b)
{
    if ($a->z != $b->z) {
        return false;
    }
    return abs($a->x - $b->x) == 1 && abs($a->y - $b->y) == 1;
}

class CrowsNestTile extends Tile
{
    const TILE_TYPE = TILETYPE_CROWS_NEST;

    public function onNpcEnters(World $world, EffectContext $ctx)
    {
        if ($ctx->npc->getNpcType() == "BOUNCER") {
            # whenBouncerEnters: each adjacent player gains 1 heat, even through walls
            foreach ($world->getPlayerCharacters() as $pc) {
                if (adjacentIgnoringWalls($ctx->pos, $pc->pos())) {
                    $world->addHeat($pc, 2);
                }
            }
        }
    }
}

// XXX: The whileHereAction will require UI input (select a tile).
class MagicShowTile extends Tile
{
    const TILE_TYPE = TILETYPE_MAGIC_SHOW;

    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        # whileHereAction: you may spend an action to move through a
        # wall bordering this tile

        # XXX:
        $validTiles = [];

        return [
            [
                "title" => "Move through wall",
                "name" => "moveThroughWall",
                "action" => "actWhileHere",
                "params" => [
                    [
                        "paramType" => "tile",
                        "validTiles" => $validTiles,
                    ],
                ],
            ],
        ];
    }
}

// XXX: The whileHereAction will require UI input (select a tile).
class RevolvingDoorTile extends Tile
{
    const TILE_TYPE = TILETYPE_REVOLVING_DOOR;

    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        # whileHereAction: you may spend 2 actions to jump to a
        # diagonal tile (through walls)

        # XXX:
        $validTiles = [];

        return [
            [
                "title" => "Jump diagonally",
                "name" => "jumpDiagonally",
                "action" => "actWhileHere",
                "params" => [
                    [
                        "paramType" => "tile",
                        "validTiles" => $validTiles,
                    ],
                ],
            ],
        ];
    }

    public function onWhileHereAction(World $world, $actionParams)
    {
        throw new \feException("no impl");
    }
}

// XXX: The whileHereAction will require UI input (select a monorail
// tile or token).
//
// XXX: We also need this special action to be available when the
// player is on a tile with a monorail token.
class MonorailTile extends Tile
{
    const TILE_TYPE = TILETYPE_MONORAIL;

    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        # whileHereAction: you may spend an action to jump to another
        # monorail

        # XXX:
        $validTiles = [];

        return [
            [
                "title" => "Ride monorail",
                "name" => "rideMonorail",
                "action" => "actWhileHere",
                "params" => [
                    [
                        "paramType" => "tile",
                        "validTiles" => $validTiles,
                    ],
                ],
            ],
        ];
    }

    public function onWhileHereAction(World $world, $actionParams)
    {
        throw new \feException("no impl");
    }
}

// XXX: We also need this special action to be available when the
// player is on a tile with an escalator token.
class EscalatorTile extends Tile
{
    const TILE_TYPE = TILETYPE_ESCALATOR;

    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        # whileHereAction: you may spend an action to jump to the tile on the
        #   other floor in the same row/column
        return [
            [
                "title" => "Ride escalator",
                "name" => "rideEscalator",
                "action" => "actWhileHere",
                "params" => [],
            ],
        ];
    }

    public function onWhileHereAction(World $world, $actionParams)
    {
        throw new \feException("no impl");
    }
}

class OwnersOfficeTile extends Tile
{
    const TILE_TYPE = TILETYPE_OWNERS_OFFICE;

    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        # whileHereAction: you may spend an action to move 1 die from here to the
        #   safe
        #
        # TODO: We could not show this, or show this disabled, if
        #   there are no dice on this tile.
        return [
            [
                "title" => "Move die to safe",
                "name" => "moveDieToSafe",
                "action" => "actWhileHere",
                "params" => [],
            ],
        ];
    }

    public function onWhileHereAction(World $world, $actionParams)
    {
        throw new \feException("no impl");
    }
}

class SafeTile extends Tile
{
    const TILE_TYPE = TILETYPE_SAFE;

    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        # whileHereAction: you may spend an action to roll all of the dice here,
        #   and then return 1 of them to the owners' office
        #
        # TODO: We could not show this, or show this disabled, if
        #   there are no dice on this tile.
        return [
            [
                "title" => "Crack safe",
                "name" => "crackSafe",
                "action" => "actWhileHere",
                "params" => [],
            ],
        ];
    }

    public function onWhileHereAction(World $world, $actionParams)
    {
        throw new \feException("no impl");
    }
}
