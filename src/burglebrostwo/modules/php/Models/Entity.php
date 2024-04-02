<?php

namespace BurgleBrosTwo\Models;

use BurgleBrosTwo\Interfaces\World;

use BurgleBrosTwo\Models\EffectContext;

abstract class Entity
{
    public int $id;
    public string $state;

    // N.B.: This is null only when the entity is despawned (not on the board).
    public ?Position $pos;

    // XXX: The raw database row.
    public $row;

    // XXX: Right now, these aren't implemented super consistently;
    // they're triggered when NPCs are entering a tile, or when PCs
    // are entering a tile.  Non-NPC entities entering a tile won't
    // trigger them.
    abstract public function onMeetsPc(World $world, EffectContext $ctx);

    abstract public function onReveal(World $world, EffectContext $ctx);
}

trait EntityManager
{
    // XXX: $entityClass is optional and provides filtering if supplied
    //
    // XXX: Returns []Entity; but there doesn't seem to be a good way
    //   to indicate that in PHP
    public function getEntitiesByPos(Position $pos, ?string $entityClass = null)
    {
        return array_map(function ($row) {
            return $this->entityFromRow($row);
        }, $this->rawGetEntitiesByPos($pos, $entityClass));
    }

    public function getEntity(int $entityId): Entity
    {
        return $this->entityFromRow($this->rawGetEntity($entityId));
    }

    protected function entityFromRow($row): Entity
    {
        // XXX: We assume the using class implements this; should refactor.
        switch (self::getEntityClass($row["entity_type"])) {
            case ENTITYCLASS_CHIP:
                return $this->chipEntityFromRow($row);
            case ENTITYCLASS_CHARACTER:
                return $this->characterEntityFromRow($row);
            case ENTITYCLASS_DESTINATION:
                return $this->destinationEntityFromRow($row);
            default:
                throw new \feException(
                    "Unexpected ENTITYCLASS_* in EntityManager::entityFromRow(): entity type = " .
                        $row["entity_type"]
                );
        }
    }

    protected function chipEntityFromRow($row)
    {
        $pos = Position::fromRow($row);

        // XXX: We should only need to do this once, and then we
        // should be able to cache it.
        $classByType = [];
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, "BurgleBrosTwo\Models\Entity")) {
                $rc = new \ReflectionClass($class);
                if (!$rc->isAbstract()) {
                    $classByType[$class::ENTITY_TYPE] = $rc;
                }
            }
        }

        $ret = $classByType[$row["entity_type"]]->newInstance();
        $ret->id = intval($row["id"]);
        $ret->pos = $pos;
        $ret->row = $row;
        $ret->state = $row["state"];
        return $ret;
    }

    protected function characterEntityFromRow($row)
    {
        $pos = Position::fromRow($row);

        // XXX: We should only need to do this once, and then we
        // should be able to cache it.
        $classByType = [];
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, "BurgleBrosTwo\Models\Entity")) {
                $rc = new \ReflectionClass($class);
                if (!$rc->isAbstract()) {
                    $classByType[$class::ENTITY_TYPE] = $rc;
                }
            }
        }

        $ret = $classByType[$row["entity_type"]]->newInstance();
        $ret->id = intval($row["id"]);
        $ret->pos = $pos;
        $ret->row = $row;
        $ret->state = $row["state"];

        // XXX: destination_entity_id

        return $ret;
    }

    protected function destinationEntityFromRow($row)
    {
        $pos = Position::fromRow($row);

        $classByType = [];
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, "BurgleBrosTwo\Models\Entity")) {
                $rc = new \ReflectionClass($class);
                if (!$rc->isAbstract()) {
                    $classByType[$class::ENTITY_TYPE] = $rc;
                }
            }
        }

        $ret = $classByType[$row["entity_type"]]->newInstance();
        $ret->id = intval($row["id"]);
        $ret->pos = $pos;
        $ret->row = $row;
        $ret->state = $row["state"];

        // XXX: destination_entity_id

        return $ret;
    }
}
