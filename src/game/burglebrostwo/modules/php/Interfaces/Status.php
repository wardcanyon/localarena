<?php

abstract class Status
{
}

abstract class TableStatus extends Status
{
}

// Table statuses:
//
// - Number of steaks the tiger has eaten (?)

abstract class PcStatus extends Status
{
}

class NoRunningStatus extends PcStatus
{
    // From the pool card "No Running": "at the end of this turn, both
// bouncers take a turn, but each bouncer only moves 2 spaces."
//
// Should be consumed by the logic in TurnOrder that puts NPC
// turns on the turn stack.  The "only moves 2 spaces" logic is
// accomplished by the `MovementChangeStatus`.
}

class DubiousMeetingStatus extends PcStatus
{
    // From the lounge card "Dubious Meeting"; while a PC has this
// status, they cannot peek.  It ends when they draw another event
// card.
}

class SaleswomanStatus extends PcStatus
{
    // From the "Saleswoman" chip.  The PC can't move unless there's
// another PC on the same tile (XXX: get detailed rules).
// This status should be removed as soon as either the chip or the
// PC does move (or the chip is discarded).
// XXX: Can we have some per-turn invariant check here, to make
// sure that this status is removed when appropriate?
}

// From Heist #6
class GravityBootsStatus extends PcStatus
{
}

abstract class NpcStatus extends Status
{
}

// Indicates that the NPC gets more or fewer movement actions than
// normal.
class MovementChangeStatus extends NpcStatus
{
    // XXX: For this status, we probably want to have something that
// lets the players figure out why it's there.  Could be a
// different name/appearance depending on its source?
// Uses of this:
// - "No Running" pool event; bouncers only get 2 moves on their
//   next turns.
}

// The NPC will stop if they reach a particular destination.  The
// status expires at the end of the current PC turn regardless.
class StopAtDestinationStatus extends NpcStatus
{
    // Uses:
// - The Buffet tile says that the Bouncer moves 3 spaces (rather
//   than the usual 1) towards its destination, but stops if it
//   reaches it.
}

// When the NPC is HUNTING, their destination is always the given
// entity, rather than the closest player.
//
// Uses: Heist #3, for the second-floor bouncer.
class HuntingEntityStatus extends NpcStatus
{
}
