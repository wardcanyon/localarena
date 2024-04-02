<?php

namespace BurgleBrosTwo\Models;

use BurgleBrosTwo\Interfaces\World;
use BurgleBrosTwo\Models\EffectContext;

abstract class Chip extends Entity
{
    public $row; // XXX: the raw DB row; remove as we flesh out these classes
}

class DrunkChip extends Chip
{
    const ENTITY_TYPE = ENTITYTYPE_CHIP_DRUNK;

    /* CTJ variant: nullifies the effects of any other chips except
     * other DRUNK chips */

    /* CTJ variant: when a player leaves the tile, at most one DRUNK
     * chip will follow the PC */

    /* CTJ variant: cannot use "while here" actions while on a tile
     * with the DRUNK */

    /* CTJ variant: players on the same tile may spend 1 action to
     * move the chip */

    public function onMeetsPc(World $world, EffectContext $ctx)
    {
        // (1st Floor:) When the player meets a Drunk, they continue
        // on in the same direction.  If they happen to be moving
        // diagonally due to an unusual game effect (e.g. a Revolving
        // Door), they continue moving diagonally.  If they can't
        // move, including vertically, as a result of the drunk, they
        // don't move but the Drunk is still discarded.

        // TODO: impl

        throw new \feException("no impl");
    }

    public function onReveal(World $world, EffectContext $ctx)
    {
        if ($ctx->triggeringAction == "PEEK") {
            $world->despawnEntity($this);
        }
    }
}

class SaleswomanChip extends Chip
{
    const ENTITY_TYPE = ENTITYTYPE_CHIP_SALESWOMAN;

    /* CTJ variant: prevents players from taking the MOVE action */

    /* CTJ variant: players on same tile may spend 3 actions to move
     * the chip */

    public function onMeetsPc(World $world, EffectContext $ctx)
    {
        // This is intentionally no-op; being on the same tile as a
        // Saleswoman changes the character's ability to act on their
        // turn, but there's no immediate effect.

        // XXX: TODO: Do we want to implement this via statuses?
    }

    public function onReveal(World $world, EffectContext $ctx)
    {
        if ($ctx->triggeringAction == "PEEK") {
            $world->despawnEntity($this);
        }
    }
}

class PrimaDonnaChip extends Chip
{
    const ENTITY_TYPE = ENTITYTYPE_CHIP_PRIMADONNA;

    public function onMeetsPc(World $world, EffectContext $ctx)
    {
        throw new \feException("no impl");
        // (normal)
        $world->despawnEntity($this);

        // (CTJ variant) lose one action
    }

    public function onReveal(World $world, EffectContext $ctx)
    {
        // (normal) Jump the PC to the chip's tile and discard this
        // chip.
        $world->jumpEntity($ctx->getPcEntity(), $this->pos);
        $world->despawnEntity($this);
    }
}

class UndercoverChip extends Chip
{
    const ENTITY_TYPE = ENTITYTYPE_CHIP_UNDERCOVER;

    public function onMeetsPc(World $world, EffectContext $ctx)
    {
        throw new \feException("no impl");
        // (normal)
        $world->despawnEntity($this);

        // (CTJ) if there is a bouncer in the same row or column as
        // the undercover chip, the PC gains 2 heat
    }

    public function onReveal(World $world, EffectContext $ctx)
    {
        // // (normal) if PEEK, bouncer jumps to the chip's tile &
        // // discard this chip
        if ($ctx->triggeringAction == "PEEK") {
            // foreach bouncer on this floor...
            // $this->world->jumpEntity(...);
            $world->despawnEntity($this);
        }
    }
}

class MoleChip extends Chip
{
    const ENTITY_TYPE = ENTITYTYPE_CHIP_MOLE;

    public function onMeetsPc(World $world, EffectContext $ctx)
    {
        // (Normal & CTJ variant) This is a no-op; blue chips don't
        // trigger effects and are only discarded once used.
    }

    public function onReveal(World $world, EffectContext $ctx)
    {
        // (Normal & CTJ variant) This is a no-op; blue chips don't
        // trigger effects and are only discarded once used.
    }
}

class CrowdChip extends Chip
{
    const ENTITY_TYPE = ENTITYTYPE_CHIP_CROWD;

    public function onMeetsPc(World $world, EffectContext $ctx)
    {
        // (Normal & CTJ variant) This is a no-op; blue chips don't
        // trigger effects and are only discarded once used.
    }

    public function onReveal(World $world, EffectContext $ctx)
    {
        // (Normal & CTJ variant) This is a no-op; blue chips don't
        // trigger effects and are only discarded once used.
    }
}
