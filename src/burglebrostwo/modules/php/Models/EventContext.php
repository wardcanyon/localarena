<?php

namespace BurgleBrosTwo\Models;

// XXX: Deprecate in favor of `EffectContext`?
class EventContext
{
    public PlayerCharacter $pc; // Set iff the event is a PC-related event.
    public Npc $npc; // Set iff the event is an NPC-related event.

    public Position $pos;
}
