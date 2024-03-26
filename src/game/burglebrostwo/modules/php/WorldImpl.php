<?php

namespace BurgleBrosTwo;

use BurgleBrosTwo\Models\Position;
use BurgleBrosTwo\Models\Entity;
use BurgleBrosTwo\Models\Tile;
use BurgleBrosTwo\Models\PlayerCharacter;
use BurgleBrosTwo\Models\Npc;

// Allows the main `BurgleBrosTwo` class to implement `World`.  Some
// parts of the `World` interface are implemented by other traits; see
// comments on the interface for details.
trait WorldImpl
{
    function jumpEntity(Entity $entity, Position $pos): void
    {
        // XXX: This is convoluted; we should just refactor this stuff
        // to use `Entity` so we don't need to re-read the data to get
        // the raw row back.

        self::DbQuery(
            "UPDATE `entity` SET " .
                $this->buildExprUpdatePos($pos) .
                " WHERE `id` = " .
                $entity->id
        );
        $this->notifyEntityUpdates(
            $this->rawGetEntity($entity->id),
            /*msg=*/ ""
        );
    }

    function discardEntity(Entity $entity): void
    {
        throw new \feException("no impl for discardEntity");
        $entity->pos = null;
        $this->updateEntity($entity->id, [
            "pos_x" => null,
            "pos_y" => null,
            "pos_z" => null,
        ]);
        // XXX: send notif
    }

    function getPlayerCharacters()
    {
        return PlayerCharacter::getAll($this);
    }

    function addCountingCubes(Tile $tile, int $n): void
    {
        $tile->counting_cubes += $n;
        $this->updateTile($tile->id, [
            "counting_cubes" => $tile->counting_cubes,
        ]);
        // XXX: send notif
    }

    function setCountingCubes(Tile $tile, int $n): void
    {
        $tile->counting_cubes = $n;
        $this->updateTile($tile->id, [
            "counting_cubes" => $tile->counting_cubes,
        ]);
        // XXX: send notif
    }

    // XXX: Do we need this, or can we read directly off $tile?
    function getCountingCubes(Tile $tile): int
    {
        return $tile->counting_cubes;
    }

    function addHeat(PlayerCharacter $pc, int $n): void
    {
        $pc->heat += $n;
        $this->updatePc($pc->id, ["heat" => $pc->heat]);
        // XXX: send notif

        // XXX: also, when adding heat, if the player is at >= 6, we
        // should give one action window and then the players lose the
        // game.  do this via a "check-heat" effect?
    }

    function setHeat(PlayerCharacter $pc, int $n): void
    {
        $pc->heat = $n;
        $this->updatePc($pc->id, ["heat" => $pc->heat]);
        // XXX: send notif
    }

    // XXX: Do we need this, or can we read directly off $pc?
    function getHeat(PlayerCharacter $pc): int
    {
        return $pc->heat;
    }

    function triggerCommotion(Position $pos, int $bouncerMovement = 1): void
    {
        // XXX: this is going to need to transition to the resolve-effect state
        // throw new \feException('no impl for triggerCommotion');
        $this->pushOnResolveStack([
            [
                "effectType" => "commotion",
                "pos" => $pos->toArray(),
                "bouncerMovement" => $bouncerMovement,
            ],
        ]);
        $this->gamestate->nextState("tResolveEffect");
    }

    function nextState(string $transition): void {
        $this->gamestate->nextState($transition);
    }

    function getPlayerCharacterByEntityId(int $entity_id): PlayerCharacter {
        return PlayerCharacter::fromRow($this->rawGetPlayerCharacterByEntityId($entity_id));
    }

    function getNpcByEntityId(int $entity_id): Npc {
        return Npc::fromRow($this->rawGetNpcByEntityId($entity_id));
    }
}
