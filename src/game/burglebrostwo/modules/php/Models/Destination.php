<?php

namespace BurgleBrosTwo\Models;

use BurgleBrosTwo\Interfaces\World;

use BurgleBrosTwo\Models\EffectContext;

class DestinationEntity extends Entity
{
    public $row; // XXX: the raw DB row; remove as we flesh out these classes

    const ENTITY_TYPE = ENTITYTYPE_DESTINATION;

    public function onMeetsPc(World $world, EffectContext $ctx)
    {
        throw new \feException("no impl");
    }

    public function onReveal(World $world, EffectContext $ctx)
    {
        // (normal) if $ctx->triggeringAction == 'PEEK', discard
    }
}
