<?php

namespace BurgleBrosTwo\Models;

use BurgleBrosTwo\Interfaces\World;

use BurgleBrosTwo\Models\EffectContext;

abstract class NpcEntity extends Entity
{
    public $row; // XXX: the raw DB row; remove as we flesh out these classes

    abstract public function getNpcType(): string;
}

class BouncerNpcEntity extends NpcEntity
{
    const ENTITY_TYPE = ENTITYTYPE_CHARACTER_BOUNCER;

    public function getNpcType(): string
    {
        return "BOUNCER";
    }

    public function onMeetsPc(World $world, EffectContext $ctx)
    {
        // XXX: Need to add code to handle crowd chips/tokens, etc.

        $world->addHeat($ctx->pc, 2);

        // XXX: this is not part of the world interface
        $world->notifyAllPlayers(
            "debugBouncerSpotsPc",
            clienttranslate("The bouncer has spotted a player! +2 heat!"),
            [
                // XXX: add player name, floor number, position, etc.
            ]
        );
    }

    public function onReveal(World $world, EffectContext $ctx)
    {
        // (normal) if $ctx->triggeringAction == 'PEEK', discard
    }
}

class Npc
{
    public int $id;
    public NpcEntity $entity;
    public DestinationEntity $destination_entity;

    // XXX: the raw DB row; eliminate this as we flesh out this class
    public $row;

    public function getNpcType(): string
    {
        return $this->entity->getNpcType();
    }

    // XXX: How do we want to expose the data-layer code to this?
    //   ($gameCtx is what we have for now.)
    public static function getById($gameCtx, int $npcId)
    {
        $that = new Npc();

        $row = $gameCtx->rawGetNpc($npcId);
        $that->row = $row;

        $that->id = intval($row["id"]);

        $that->entity = $gameCtx->getEntity(intval($row["entity_id"]));

        // XXX: not all NPC types will have this property set (e.g. tigers)
        $that->destination_entity = $gameCtx->getEntity(
            intval($row["destination_entity_id"])
        );
        return $that;
    }

    public function pos(): Position
    {
        return $this->entity->pos;
    }
}
