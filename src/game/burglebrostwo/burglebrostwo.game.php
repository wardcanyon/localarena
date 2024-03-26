<?php
/**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  otifyentityupdates*
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  *
  * burglebrostwo.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  */

$swdNamespaceAutoload = function ($class) {
    $classParts = explode("\\", $class);
    if ($classParts[0] == "BurgleBrosTwo") {
        array_shift($classParts);
        $file =
            dirname(__FILE__) .
            "/modules/php/" .
            implode(DIRECTORY_SEPARATOR, $classParts) .
            ".php";
        if (file_exists($file)) {
            require_once $file;
        } else {
            var_dump("Cannot find file: " . $file);
        }
    }
};
spl_autoload_register($swdNamespaceAutoload, true, true);

require_once APP_GAMEMODULE_PATH . "module/table/table.game.php";

require_once "modules/php/card_data.inc.php";
require_once "modules/php/constants.inc.php";

require_once "modules/php/Models/Chip.php";
require_once "modules/php/Models/Destination.php";
require_once "modules/php/Models/Entity.php";
require_once "modules/php/Models/EventCard.php";
require_once "modules/php/Models/Npc.php";
require_once "modules/php/Models/PlayerCharacter.php";
require_once "modules/php/Models/Position.php";
require_once "modules/php/Models/Tile.php";

// use BurgleBrosTwo\Managers\Board;

use BurgleBrosTwo\Models\Chip;
use BurgleBrosTwo\Models\Entity;
use BurgleBrosTwo\Models\Position;
use BurgleBrosTwo\Models\Tile;

class BurgleBrosTwo extends Table implements BurgleBrosTwo\Interfaces\World
{
    use BurgleBrosTwo\Bouncer;
    use BurgleBrosTwo\ClientRender;
    use BurgleBrosTwo\DataLayer;
    use BurgleBrosTwo\GameEffects;
    use BurgleBrosTwo\GameFlow;
    use BurgleBrosTwo\GameOptions;
    use BurgleBrosTwo\Setup;
    use BurgleBrosTwo\TurnOrder;
    use BurgleBrosTwo\WorldImpl;

    use BurgleBrosTwo\Models\EntityManager;
    use BurgleBrosTwo\Models\TileManager;

    use BurgleBrosTwo\Utilities\GameState;

    use BurgleBrosTwo\States\ActionWindow;
    use BurgleBrosTwo\States\CharacterSelection;
    use BurgleBrosTwo\States\CharacterSelectionRoundEnd;
    use BurgleBrosTwo\States\FinishSetup;
    use BurgleBrosTwo\States\NextCharacter;
    use BurgleBrosTwo\States\NpcTurn;
    use BurgleBrosTwo\States\PlaceEntranceTokens;
    use BurgleBrosTwo\States\PlayerTurn;
    use BurgleBrosTwo\States\PlayerTurnEnds;
    use BurgleBrosTwo\States\PlayerTurnEnterMap;
    use BurgleBrosTwo\States\ResolveEffect;

    function __construct()
    {
        parent::__construct();
        self::initGameStateLabels([
            "optionCharacterSelection" => OPTION_CHARACTER_SELECTION,
            // "optionCharacterVariants" => OPTION_CHARACTER_VARIANTS,
            "optionSuspicion" => OPTION_SUSPICION,
            "optionFinale" => OPTION_FINALE,
            "optionMultihanded" => OPTION_MULTIHANDED,
            "optionWallPlacement" => OPTION_WALL_PLACEMENT,
            "optionWallRerolling" => OPTION_WALL_REROLLING,
            "optionVariantDeadDrops" => OPTION_VARIANT_DEAD_DROPS,
            "optionVariantCasingTheJoint" => OPTION_VARIANT_CASING_THE_JOINT,
        ]);
    }

    protected function getGameName()
    {
        return "burglebrostwo";
    }

    // -----------
    // BGA framework entry points
    // -----------

    // Get all datas (complete reset request from client side)
    protected function getAllDatas()
    {
        // XXX: things to add ...
        // - character hands
        // - character statuses
        // - finale, if visible, and finale active/inactive state
        // - discard piles & number of cards deck (plus number of
        //   cards moved for patrol decks?)
        // - patrol route(s)
        return [
            "gamemap" => self::renderGameMapForClient(
                self::rawGetTiles(),
                self::getWalls()
            ),
            "characters" => self::renderPlayerCharactersForClient(
                self::getPlayerCharacters()
            ),
            "entities" => self::renderEntitiesForClient(self::getEntities()),
            "decks" => self::getAndRenderAllDecksForClient(),
            "gameFlowSettings" => self::getGameFlowSettingsForClient(),
            "finale" => self::renderFinaleForClient(),
            // N.B.: This includes the patrol-path data for bouncer
            // NPCs.
            "npcs" => self::renderNpcsForClient(self::getNpcs()),
            "tableStatuses" => self::renderStatusesForClient(
                self::getGameStateJson(GAMESTATE_JSON_TABLE_STATUSES)
            ),
        ];
    }

    function getGameFlowSettingsForClient()
    {
        $playerId = self::getCurrentPlayerId();
        $stepping = self::getGameStateJson(GAMESTATE_JSON_NPC_STEPPING);
        return [
            "stepping" => $stepping[$playerId],
        ];
    }

    function getGameProgression()
    {
        return 42;
    }

    // -----------
    // Misc
    // -----------

    private function getEntityClass($entityType)
    {
        switch ($entityType) {
            case ENTITYTYPE_TOKEN_CROWD:
            case ENTITYTYPE_TOKEN_OUTOFORDER:
            case ENTITYTYPE_TOKEN_DISGUISE:
            case ENTITYTYPE_TOKEN_ENTRANCE:
            case ENTITYTYPE_TOKEN_ESCALATOR:
            case ENTITYTYPE_TOKEN_HANDPRINT:
            case ENTITYTYPE_TOKEN_MONORAIL:
            case ENTITYTYPE_TOKEN_RAVEN:
            case ENTITYTYPE_TOKEN_STEAK:
            case ENTITYTYPE_TOKEN_SWAT:
                return ENTITYCLASS_TOKEN;
            case ENTITYTYPE_CHIP_CROWD:
            case ENTITYTYPE_CHIP_DRUNK:
            case ENTITYTYPE_CHIP_MOLE:
            case ENTITYTYPE_CHIP_PRIMADONNA:
            case ENTITYTYPE_CHIP_SALESWOMAN:
            case ENTITYTYPE_CHIP_UNDERCOVER:
                return ENTITYCLASS_CHIP;
            case ENTITYTYPE_DESTINATION:
                return ENTITYCLASS_DESTINATION;
            case ENTITYTYPE_CHARACTER_PLAYER:
            case ENTITYTYPE_CHARACTER_BOUNCER:
            case ENTITYTYPE_CHARACTER_TIGER:
                return ENTITYCLASS_CHARACTER;
            default:
                throw new Exception("Unexpected entity type: $entityType");
        }
    }

    // -----------
    // Action handler dispatchers
    // -----------

    // XXX: These could probably be automatically generated by walking
    // the state traits and looking at which action handlers they
    // define.

    function onActSelectTile(Position $pos)
    {
        self::trace(
            "onActSelectTile(): pos=" . print_r($pos, /*return=*/ true)
        );

        // switch on state ...
        switch ($this->gamestate->state()["name"]) {
            case "stPlaceEntranceTokens":
                return self::onActSelectTile_stPlaceEntranceTokens($pos);
            case "stPlayerTurnEnterMap":
                return self::onActSelectTile_stPlayerTurnEnterMap($pos);
            default:
                throw new feException("Unexpected state.");
        }
    }

    function onActPlayCard($cardId)
    {
        // switch on state ...
        switch ($this->gamestate->state()["name"]) {
            case "stCharacterSelection":
                return self::onActPlayCard_stCharacterSelection($cardId);
            default:
                throw new feException("Unexpected state.");
        }
    }

    function onActPass()
    {
        // switch on state ...
        switch ($this->gamestate->state()["name"]) {
            case "stCharacterSelection":
                return self::onActPass_stCharacterSelection();
            default:
                throw new feException("Unexpected state.");
        }
    }

    function onActMove(Position $pos)
    {
        switch ($this->gamestate->state()["name"]) {
            case "stPlayerTurn":
                return self::onActMove_stPlayerTurn($pos);
            default:
                throw new feException("Unexpected state.");
        }
    }

    function onActPeek(Position $pos)
    {
        switch ($this->gamestate->state()["name"]) {
            case "stPlayerTurn":
                return self::onActPeek_stPlayerTurn($pos);
            default:
                throw new feException("Unexpected state.");
        }
    }

    // -----------
    // Misc
    // -----------

    // XXX: move to utils
    // XXX: DEPRECATED: Position::fromRow()
    function posFromRow($row)
    {
        return [
            intval($row["pos_x"]),
            intval($row["pos_y"]),
            intval($row["pos_z"]),
        ];
    }

    function varDumpToString($var)
    {
        ob_start();
        var_dump($var);
        $result = ob_get_clean();
        return $result;
    }

    function notifyDebug($scope, $msg): void
    {
        // XXX: make this more configurable
        if ($scope == "ResolveEffect") {
            return;
        }

        self::notifyAllPlayers("debugMessage", $scope . ": " . $msg, []);
    }

    // Used both when entities are actually created (`createEntity()`)
    // and when they are spawned onto the board (i.e. when their
    // position goes from NULL / out-of-play to in-play).  Causes the
    // client to become aware of the entity and to draw it.
    //
    // N.B.: When $msg is empty, no entry appears in the client-side
    // log, which can be useful for avoiding spam when sending a
    // number of notifications.
    function notifyEntitySpawns($entity, $msg, $silent = false)
    {
        self::notifyAllPlayers("entitySpawns", clienttranslate($msg), [
            "silent" => $silent,
            "entity" => self::renderEntityForClient($entity),
        ]);
    }

    function notifyEntityUpdates($entity, $msg)
    {
        self::notifyAllPlayers("entityUpdates", clienttranslate($msg), [
            "entity" => self::renderEntityForClient($entity),
        ]);
    }

    function notifyTileUpdates($tile, $msg)
    {
        self::notifyAllPlayers("tileUpdates", clienttranslate($msg), [
            "tile" => self::renderTileForClient($tile),
        ]);
    }

    function notifyWallSpawns($wall, $msg, $silent = false)
    {
        self::notifyAllPlayers("wallSpawns", clienttranslate($msg), [
            // XXX: Remove "silent", which we don't use any longer
            // on the client; if you want a silent notification,
            // give an empty $msg instead.
            "silent" => $silent,
            "wall" => self::renderWallForClient($wall),
        ]);
    }

    function createEntity(
        $entityType,
        Position $pos,
        $msg,
        $state = "VISIBLE",
        $silent = false,
        $sendNotif = true
    ) {
        $values = [];
        $values[] =
            '("' .
            $entityType .
            '", "' .
            $state .
            '", ' .
            $pos->x .
            ", " .
            $pos->y .
            ", " .
            $pos->z .
            ")";
        self::DbQuery(
            "INSERT INTO entity (entity_type, state, pos_x, pos_y, pos_z) VALUES " .
                implode(",", $values)
        );
        $entityId = self::DbGetLastId();

        if ($sendNotif) {
            $this->notifyEntitySpawns(
                [
                    // N.B.: To avoid needing to read this back, we
                    // create an array that looks a lot like what we'd
                    // get from the database if we did.
                    "id" => $entityId,
                    "entity_type" => $entityType,
                    "state" => $state,
                    "pos_x" => $pos->x,
                    "pos_y" => $pos->y,
                    "pos_z" => $pos->z,
                ],
                $msg,
                $silent
            );
        }

        return $entityId;
    }

    // XXX: should this be a generic "update entity" function?
    function moveEntity(Entity $entity, Position $pos): void
    {
        $entity->pos = $pos;  // XXX: is this what we want?
        self::DbQuery(
            "UPDATE `entity` SET " .
                $this->buildExprUpdatePos($pos) .
                " WHERE `id` = " .
                $entity->id,
        );
        $this->notifyEntityUpdates(
            $this->rawGetEntity($entity->id),
            /*msg=*/ ""
        );
    }

    // -----------
    // Zombie players
    // -----------

    function zombieTurn($state, $active_player)
    {
        if ($state["name"] == "playerTurn") {
            $this->gamestate->nextState("tZombiePass");
        } else {
            throw new feException(
                "Zombie mode not supported at this game state: " .
                    $state["name"]
            );
        }
    }

    // -----------
    // Misc things that weren't in the right spot, but still need a home
    // -----------

    function revealTile(Tile $tile): void
    {
        self::DbQuery(
            'UPDATE `tile` SET state = "VISIBLE" WHERE ' .
                $this->buildExprWherePos($tile->pos)
        );
        $this->notifyTileUpdates($this->rawGetTile($tile->id), /*msg=*/ "");
    }

    function revealChip(Chip $chip): void
    {
        self::DbQuery(
            'UPDATE `entity` SET state = "VISIBLE" WHERE `id`=' . $chip->id
        );
        // XXX: this part is "pre-OOP":
        $this->notifyEntityUpdates(
            $this->rawGetEntity($chip->id),
            /*msg=*/ "A chip is revealed."
        );
    }

}
