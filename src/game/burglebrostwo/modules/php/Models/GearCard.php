<?php

abstract class GearCard
{
    // abstract public function onResolving(World $world, EffectContext $ctx);

    public function onResolving(World $world, EffectContext $ctx)
    {
        throw new \feException("Gear card not implemented.");
    }
}

// XXX: For these, perhaps we need the client to indicate that the
// intention to use a gear card so that we can reply with a list of
// valid targets/etc.

// Card backs
//
// Unlike the front side of a gear card, these can only be played
// during the character's turn and they take an action (but they do
// not need to be prepped).

class MakeCommotionGearCard extends GearCard
{
    const CARD_TYPE = "make-commotion";

    public function onResolving(World $world, EffectContext $ctx)
    {
        throw new \feException("Gear card not implemented.");
    }
}

class GiveActionGearCard extends GearCard
{
    const CARD_TYPE = "give-action";

    public function onResolving(World $world, EffectContext $ctx)
    {
        throw new \feException("Gear card not implemented.");
    }
}

class TakeHeatGearCard extends GearCard
{
    const CARD_TYPE = "take-heat";

    public function onResolving(World $world, EffectContext $ctx)
    {
        throw new \feException("Gear card not implemented.");
    }
}

// Juicer

class AdrenalineGearCard extends GearCard
{
    const CARD_TYPE = "adrenaline";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // "Set the current player’s heat to 5. The current player
        // gains 3 extra actions this turn."
        throw new \feException("Gear card not implemented.");
    }
}

class BingoGearCard extends GearCard
{
    const CARD_TYPE = "bingo";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // "Change the destination of a bouncer to a tile in the same
        // row or column as his current destination."
        throw new \feException("Gear card not implemented.");
    }
}

class FaintingGearCard extends GearCard
{
    const CARD_TYPE = "fainting";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // "The current player ends their turn. The bouncer on the
        // same floor jumps to the tile with the current player,
        // giving no heat to any players there. This bouncer skips his
        // turn."
        throw new \feException("Gear card not implemented.");
    }
}

class CrybabyGearCard extends GearCard
{
    const CARD_TYPE = "crybaby";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // "Make a commotion on any tile and a commotion on the tile
        // with the same row and column on the other floor."
        throw new \feException("Gear card not implemented.");
    }
}

// Hacker

class LoopFootageGearCard extends GearCard
{
    const CARD_TYPE = "loop-footage";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // "Remove all counting cubes from 1 Cashier Cages, Buffet, or
        // Surveillance tile."
        throw new \feException("Gear card not implemented.");
    }
}

class OutOfOrderGearCard extends GearCard
{
    const CARD_TYPE = "out-of-order";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // "Place the out-of-order token on any face-up tile. That
        // tile has no effect for the rest of the game."
        throw new \feException("Gear card not implemented.");
    }
}

class RadioInterference extends GearCard
{
    const CARD_TYPE = "radio-interference";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // "Move either bouncer to any tile adjacent to their current
        // tile. (Not through walls. Trigger any When Bouncer Enters
        // effects.)"
        throw new \feException("Gear card not implemented.");
    }
}

class HackGearCard extends GearCard
{
    const CARD_TYPE = "hack";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // "Prevent a commotion."
        throw new \feException("Gear card not implemented.");
    }
}
