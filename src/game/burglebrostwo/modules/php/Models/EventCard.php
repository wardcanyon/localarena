<?php

abstract class EventCard
{
    abstract public function onResolving(World $world, EffectContext $ctx);
}

abstract class PoolEventCard extends EventCard
{
    const EVENT_DECK = "POOL";
}

class SplashZoneEventCard extends PoolEventCard
{
    const CARD_TYPE = "splash-zone";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // Discard 1 of your face-up, prepped gear cards of your
        // choice. Take 1 gear card at random from among all
        // characters not in play, and prep it without spending an
        // action.

        // XXX: requires UI: choose a face-up, prepped gear card

        // - discard chosen card
        // - give random gear card & mark it prepped
    }
}

class CannonballEventCard extends PoolEventCard
{
    const CARD_TYPE = "cannonball";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // Make a commotion on the current player’s tile and on the
        // tile in the same row and column on the other floor.

        $world->triggerCommotion($ctx->pos);

        $otherFloor = $ctx->pos;
        $otherFloor->z = 1 - $otherFloor->z;
        $world->triggerCommotion($ctx->otherFloor);
    }
}

class PoolPrankEventCard extends PoolEventCard
{
    const CARD_TYPE = "pool-prank";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // Take 1 random discarded patrol card from the first
        // floor. Set the destination of each bouncer to the tile on
        // his floor in the row and column shown on the card. Then
        // return the card to the discard pile.
    }
}

class TradeShiftsEventCard extends PoolEventCard
{
    const CARD_TYPE = "trade-shifts";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // Take 2 cards from the patrol deck that has more
        // cards. Without looking at them, put them at the bottom of
        // the other patrol deck.
    }
}

class IdentityTheftEventCard extends PoolEventCard
{
    const CARD_TYPE = "identity-theft";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // Swap heat with the next player.
    }
}

class GoForASwimEventCard extends PoolEventCard
{
    const CARD_TYPE = "go-for-a-swim";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // Draw and discard a patrol card from either deck. The
        // bouncer on that floor jumps to the tile shown. (Trigger any
        // When Bouncer Enters effects. His destination stays the
        // same.)

        // XXX: UI: select a patrol deck (or, equivalently, select a floor)
    }
}

class LifeguardTipEventCard extends PoolEventCard
{
    const CARD_TYPE = "lifeguard-tip";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // The bouncer on the current player’s floor jumps to
        // any tile adjacent to the current player’s tile,
        // your choice. (Trigger any When Bouncer Enters
        // effects.)

        // XXX: UI: select a tile from the eligible set
    }
}

class NoRunningEventCard extends PoolEventCard
{
    const CARD_TYPE = "no-running";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // At the end of this turn, both bouncers take a turn, but
        // each bouncer only moves 2 spaces.

        // XXX: this is tricky; implement this via the NoRunningStatus
        //   and the MovementChangeStatus
    }
}

abstract class LoungeEventCard extends EventCard
{
    const EVENT_DECK = "POOL";
}

class DirectionsEventCard extends LoungeEventCard
{
    const CARD_TYPE = "directions";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // Peek at any tile on either floor.

        // XXX: UI: select any tile
    }
}

class HappyHourEventCard extends LoungeEventCard
{
    const CARD_TYPE = "happy-hour";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // Set every face-up Buffet, Surveillance, and Cashier Cages
        // tile to have 2 counting cubes.
    }
}

class ChatterboxEventCard extends LoungeEventCard
{
    const CARD_TYPE = "chatterbox";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // The bouncer on this floor jumps to his
        // destination. (Trigger any When Bouncer Enters effects.)
    }
}

class EspressoBarEventCard extends LoungeEventCard
{
    const CARD_TYPE = "espresso-bar";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // The bouncer on this floor immediately moves 2 spaces. (This
        // does not reduce the spaces he moves during his turn.)
    }
}

class ShareADrinkEventCard extends LoungeEventCard
{
    const CARD_TYPE = "share-a-drink";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // Any one other player must jump to the current player’s
        // tile, ignoring any When You Enter effects.

        // XXX: UI: choose a character other than the current one
    }
}

class TipsyEventCard extends LoungeEventCard
{
    const CARD_TYPE = "tipsy";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // The bouncer on this floor jumps to his destination and sets
        // his destination to the tile he jumped from. (Trigger any
        // When Bouncer Enters effects.)
    }
}

class ThirstyEventCard extends LoungeEventCard
{
    const CARD_TYPE = "thirsty";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // The bouncer sets his destination to the current player’s tile.
    }
}

class DubiousMeetingEventCard extends LoungeEventCard
{
    const CARD_TYPE = "dubious-meeting";

    public function onResolving(World $world, EffectContext $ctx)
    {
        // Put this card in front of the current player. While this
        // card is in front of you, you cannot peek. Discard this card
        // when you draw another pool or lounge event card.
    }
}
