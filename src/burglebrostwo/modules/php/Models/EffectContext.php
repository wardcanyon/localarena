<?php

namespace BurgleBrosTwo\Models;

const TRIGGERINGACTION_MOVE = "MOVE";
const TRIGGERINGACTION_PEEK = "PEEK";
const TRIGGERINGACTION_ENTER = "ENTER";

use BurgleBrosTwo\Models\Npc;

class EffectContext
{
    // One of `TRIGGERINGACTION_*`.
    public ?string $triggeringAction;

    public int $stackDepth;

    // Set iff the event is a PC-related event.
    public PlayerCharacter $pc;
    // Set iff the event is an NPC-related event.
    public Npc $npc;

    public Position $pos;

    // XXX: This is mostly here to make it easier to do things like
    // push the effect back on the stack.  We should be very careful
    // about how we use this.
    public $rawEffect;

    // XXX: do we need this when we can just ask for $this->pc->entity now?
    public function getPcEntity()
    {
        // throw new \feException('no impl: effectcontext::getpcentity()');
        // // XXX:
        return $this->pc->entity;
    }

    public function getEntities($entityType = null)
    {
        throw new \feException("no impl: effectcontext::getentities()");
    }
}
