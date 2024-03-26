<?php

namespace BurgleBrosTwo\Models;

use BurgleBrosTwo\Interfaces\World;

use BurgleBrosTwo\Models\EffectContext;

// XXX: Rename to PlayerCharacterEntity in line with NpcEntity; add
// new PlayerCharacter class.
class PlayerCharacterEntity extends Entity
{
    const ENTITY_TYPE = ENTITYTYPE_CHARACTER_PLAYER;

    public $row; // XXX: the raw DB row; remove as we flesh out these classes

    public function onMeetsPc(World $world, EffectContext $ctx)
    {
        // This doesn't make any sense for PC entities.
    }

    public function onReveal(World $world, EffectContext $ctx)
    {
        // This doesn't make any sense for PC entities.
    }
}

class PlayerCharacter
{
    public int $id;
    public PlayerCharacterEntity $entity;
    public int $heat;
    public string $state;
    // public int $turn_order;
    public string $bro;
    // public int $player_id;

    // XXX: the raw DB row; eliminate this as we flesh out this class
    public $row;

    // XXX: How do we want to expose the data-layer code to this?
    //   ($gameCtx is what we have for now.)  Should $gameCtx be `World`?
    public static function getActive($gameCtx)
    {
        return PlayerCharacter::fromRow(
            $gameCtx,
            $gameCtx->rawGetActivePlayerCharacter()
        );
    }

    // XXX: How do we want to expose the data-layer code to this?
    //   ($gameCtx is what we have for now.)
    public static function getById($gameCtx, int $pcId)
    {
        return PlayerCharacter::fromRow(
            $gameCtx,
            $gameCtx->rawGetPlayerCharacter($pcId)
        );
    }

    public static function getAll($gameCtx)
    {
        $result = [];
        foreach ($gameCtx->rawGetPlayerCharacters() as $row) {
            $result[] = PlayerCharacter::fromRow($gameCtx, $row);
        }
        return $result;
    }

    protected static function fromRow($gameCtx, $row)
    {
        $that = new PlayerCharacter();

        $that->row = $row;

        $that->id = intval($row["id"]);
        $that->heat = intval($row["heat"]);

        $that->entity = $gameCtx->getEntity(intval($row["entity_id"]));

        $that->bro = $row["bro"];
        $that->state = $row["state"];

        return $that;
    }

    public function pos(): Position
    {
        return $this->entity->pos;
    }
}
