<?php

namespace BurgleBrosTwo\Interfaces;

use BurgleBrosTwo\Models\Position;
use BurgleBrosTwo\Models\Entity;
use BurgleBrosTwo\Models\Tile;
use BurgleBrosTwo\Models\PlayerCharacter;
use BurgleBrosTwo\Models\Npc;
use BurgleBrosTwo\Models\Chip;

interface World
{
    // These functions are implemented by `TileManager`.
    public function getTileById(int $tile_id): Tile;
    public function getTileByPos(Position $pos): Tile;

    function revealTile(Tile $tile): void;

    function getPlayerCharacters();  // Returns `PlayerCharacter[]`.
    function getPlayerCharacterByEntityId(int $entity_id): PlayerCharacter;

    function getNpcByEntityId(int $entity_id): Npc;

    function addCountingCubes(Tile $tile, int $n): void;
    function setCountingCubes(Tile $tile, int $n): void;
    function getCountingCubes(Tile $tile): int;

    function addHeat(PlayerCharacter $pc, int $n): void;
    function setHeat(PlayerCharacter $pc, int $n): void;
    function getHeat(PlayerCharacter $pc): int;

    function triggerCommotion(Position $pos, int $bouncerMovement = 1): void;

    // Resolve effect & value stacks

    // XXX: This takes an array of raw effect arrays; it's implemented
    // by the `GameEffects` trait.  We should replace this with a
    // nicer, typed wrapper that also passes along context info.
    function pushOnResolveStack($effects);
    function popFromResolveStack();

    function popFromResolveValueStack();
    function peekFromResolveValueStack();
    function pushOnResolveValueStack($value);

    // Entities

    function jumpEntity(Entity $entity, Position $pos): void;
    function despawnEntity(Entity $entity): void;
    // function createEntity();
    function getEntity(int $entity_id): Entity;
    function getEntitiesByPos(Position $pos, ?string $entity_class = null); // Returns `Entity[]`.
    function moveEntity(Entity $entity, Position $pos): void;

    // Chips

    function revealChip(Chip $chip): void;

    // Notifications

    function notifyDebug($scope, $msg): void;

    // XXX: This is defined directly in BGA framework code, on their
    // `Table` class.
    function notifyAllPlayers($notification_type, $notification_log, $notification_args);

    // gamestate->nextState()
    function nextState(string $transition): void;

    // Game-state management

    function getGameStateJson(string $gamestate_key);
    function setGameStateJson(string $gamestate_key, $value): void;
    function getGameStateInt(string $gamestate_key): int;
    function setGameStateInt(string $gamestate_key, int $value): void;

    // XXX: other things we'll probably need:
    // - add/remove statuses from characters & the table
    // - ability to interact with decks
    // - ability to indicate what data needs to be updated on clients
    // - get all tiles
    //   - ideally with some filtering options: type, face-up/down state, etc.
}
