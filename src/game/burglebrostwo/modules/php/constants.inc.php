<?php

/*
Gameplay constants
*/

const MIN_CHARACTERS = 2;
const MAX_CHARACTERS = 4;
const ENTRANCE_TOKEN_QTY = 2;
const PC_ACTIONS_PER_TURN = 4;
const NPC_ACTIONS_PER_TURN = 4;
const WALLS_PER_FLOOR = 8;

/*
Game-state values

These are used with our variable peristence system, which is similar
to BGA's (`getGameStateValue()`, `setGameStateValue()`) except that it
stores JSON blobs rather than integers.
*/

// In player-character states, this value stores the `id` of the
// character whose turn it is.  The corresponding
// `character_player.player_id` value will be
// `self::getActivePlayerId()` (that is, the active character must be
// controlled by the active player).
//
// XXX: update this; and rename this to ACTIVE_CHARACTER_ID while we're at it.
// In other states (e.g. during NPC turns), this value is meaningless.
//
// XXX: updated note:
//
// Contains two keys:
//   "character_type": one of "PLAYER", "NPC"
//   "character_id": the `id` column from the corresponding table
//
const GAMESTATE_JSON_ACTIVE_CHARACTER = "activeCharacter";

// In Burgle Bros, the player characters act in a fixed order; NPCs
// get turns when a player character ends a turn on their floor.
// There are also some game effects that can change turn order.
//
// This gamestate variable stores an ordered list of "next turns".  We
// set it at the end of each player-character turn, or when a relevant
// effect resolves; when it is empty, the next player character
// (GAMESTATE_INT_NEXT_PLAYER_CHARACTER) will go.
//
// Each entry in this array is an associative array with the following
// keys:
//   "character_type": one of "PLAYER", "NPC"
//   "character_id": the `id` column from the corresponding table
//
// The value of this variable is meaningful in every main-game state.
//
const GAMESTATE_JSON_TURN_STACK = "turnStack";

// The `character.id` of the player character who will take the next
// turn once the turn stack (GAMESTATE_JSON_TURN_STACK) is empty.
//
// The value of this variable is meaningful in every main-game state.
//
const GAMESTATE_INT_NEXT_PLAYER_CHARACTER = "nextPlayerCharacter";

// Valid only during player-character turns.
const GAMESTATE_INT_REMAINING_ACTIONS = "remainingActions";

// In Burgle Bros, players perform actions at many points during NPC
// turns.  Stopping at every one of those points and making all
// players explicitly "pass" would be annoying, however; so instead,
// we give each player an "NPC stepping" toggle that they can turn on
// or off at any point.
//
// When any player has NPC stepping enabled, the game will pause at
// each point during NPC turns where players can act, and ask those
// players to act or pass.
//
// This is stored as a map from player ID to a boolean indicating if
// that player has NPC stepping enabled or not.
//
// XXX: Need to rename this; I don't think that "NPC stepping" is
// accurate any longer.
//
const GAMESTATE_JSON_NPC_STEPPING = "npcStepping";

// The "resolve stack" describes any game effects that have triggered
// but not yet resolved.  We represent this explicitly because we need
// to be able to transition into "step" states to let players act
// (e.g. part-way through resolving the effects, or even during an NPC
// or different player's turn) without forgetting what we were doing.
//
const GAMESTATE_JSON_RESOLVE_STACK = "resolveStack";

// Used as part of the effect-resolution system to pass values back to
// parent effects (e.g. the outcome of a die roll).
//
// Each entry is an array.  There are certain keys that each value must have.
//
// - 'valueType': The only supported value type is currently
//   "dice".
// - 'productionDepth': The depth of the effect stack at the time that
//   the value was pushed onto the value stack.  This lets code
//   distinguish between the situation where the effect-resolution
//   stack is being built, and something lower in the stack happens to
//   have pushed a value of the right type; and the situation where
//   the effect-resolution stack is unwinding, and a value has been
//   returned to it by an effect higher on the stack.
//
// For "dice" values, there is a key called 'dice', which holds
// an array of integers each representing the outcome of a die.
//
const GAMESTATE_JSON_RESOLVE_VALUE_STACK = "resolveValueStack";

// Status effects that apply to the entire table, rather than to
// specific characters.
const GAMESTATE_JSON_TABLE_STATUSES = "tableStatuses";

/*
Entity types and classes
*/

const ENTITYCLASS_TOKEN = "TOKEN";
const ENTITYCLASS_CHIP = "CHIP";
const ENTITYCLASS_DESTINATION = "DESTINATION";
const ENTITYCLASS_CHARACTER = "CHARACTER";

// N.B.: These match the enum values for `entity.entity_type` in our
// database schema.
const ENTITYTYPE_TOKEN_CROWD = "TOKEN_CROWD";
const ENTITYTYPE_TOKEN_OUTOFORDER = "TOKEN_OUTOFORDER";
const ENTITYTYPE_TOKEN_DISGUISE = "TOKEN_DISGUISE";
const ENTITYTYPE_TOKEN_ENTRANCE = "TOKEN_ENTRANCE";
const ENTITYTYPE_TOKEN_ESCALATOR = "TOKEN_ESCALATOR";
const ENTITYTYPE_TOKEN_HANDPRINT = "TOKEN_HANDPRINT";
const ENTITYTYPE_TOKEN_MONORAIL = "TOKEN_MONORAIL";
const ENTITYTYPE_TOKEN_RAVEN = "TOKEN_RAVEN";
const ENTITYTYPE_TOKEN_STEAK = "TOKEN_STEAK";
const ENTITYTYPE_TOKEN_SWAT = "TOKEN_SWAT";
const ENTITYTYPE_CHIP_CROWD = "CHIP_CROWD";
const ENTITYTYPE_CHIP_DRUNK = "CHIP_DRUNK";
const ENTITYTYPE_CHIP_MOLE = "CHIP_MOLE";
const ENTITYTYPE_CHIP_PRIMADONNA = "CHIP_PRIMA-DONNA";
const ENTITYTYPE_CHIP_SALESWOMAN = "CHIP_SALESWOMAN";
const ENTITYTYPE_CHIP_UNDERCOVER = "CHIP_UNDERCOVER";
const ENTITYTYPE_DESTINATION = "DESTINATION";
const ENTITYTYPE_CHARACTER_PLAYER = "CHARACTER_PLAYER";
const ENTITYTYPE_CHARACTER_BOUNCER = "CHARACTER_BOUNCER";
const ENTITYTYPE_CHARACTER_TIGER = "CHARACTER_TIGER";

/*
Game states
*/

const ST_GAME_SETUP = 1;
const ST_GAME_END = 99;

const ST_RANDOMIZE_WALLS = 2; // XXX: no such state at the moment
const ST_CHARACTER_SELECTION = 3;
const ST_CHARACTER_SELECTION_ROUND_END = 4;
const ST_FINISH_SETUP = 5;
const ST_PLACE_ENTRANCE_TOKENS = 6;
const ST_PLAYER_TURN_ENTER_MAP = 7;
const ST_PLAYER_TURN = 10;
const ST_NPC_TURN = 11;
const ST_ACTION_WINDOW = 12;
const ST_NEXT_CHARACTER = 13;
const ST_PLAYER_TURN_ENDS = 14;
const ST_RESOLVE_EFFECT = 15;

/*
Trigger that represents the times when a player can use gear that
doesn't have specific reaction-trigger criteria.
*/
const TRIGGER_ACTION_GEAR_WINDOW = 6;

/*
Reaction triggers (or "reaction window types"?): things that can cause
players to be able to perform a special sort of action
*/

const TRIGGER_REACTION_DIE_ROLL = 1;
const TRIGGER_REACTION_COMMOTION = 2;
const TRIGGER_REACTION_CHIP_REVEALED = 3;
const TRIGGER_REACTION_PATROL_CARD_DRAWN = 4;
const TRIGGER_REACTION_EVENT_CARD_DRAWN = 5;

/*
  Tile types
*/

const TILETYPE_POOL = "POOL";
const TILETYPE_LOUNGE = "LOUNGE";
const TILETYPE_TABLE_GAMES = "TABLE-GAMES";
const TILETYPE_FRONT_DESK = "FRONT-DESK";
const TILETYPE_SURVEILLANCE = "SURVEILLANCE";
const TILETYPE_BUFFET = "BUFFET";
const TILETYPE_PIT_BOSS = "PIT-BOSS";
const TILETYPE_COUNT_ROOM = "COUNT-ROOM";
const TILETYPE_SLOTS = "SLOTS";
const TILETYPE_CASHIER_CAGES = "CASHIER-CAGES";
const TILETYPE_CROWS_NEST = "CROWS-NEST";
const TILETYPE_MAGIC_SHOW = "MAGIC-SHOW";
const TILETYPE_REVOLVING_DOOR = "REVOLVING-DOOR";
const TILETYPE_MONORAIL = "MONORAIL";
const TILETYPE_ESCALATOR = "ESCALATOR";
const TILETYPE_OWNERS_OFFICE = "OWNERS-OFFICE";
const TILETYPE_SAFE = "SAFE";

/* Constants for the Deck component */

// actual supply decks; the deck vs. discard; character hands

// const DECK_LOC_CHARACTER = 'charHand';  // arg is character index
// const DECK_LOC_DECK = 'deck';

// const DECK_LOCARG_DECK = 1;
// const DECK_LOCARG_DISCARD = 2;

/*
Game options
*/

const OPTION_CHARACTER_SELECTION = 100;
const OPTION_SUSPICION = 102;
const OPTION_FINALE = 103;
const OPTION_MULTIHANDED = 104;
const OPTION_WALL_PLACEMENT = 105;
const OPTION_WALL_REROLLING = 106;
const OPTION_VARIANT_DEAD_DROPS = 107;
const OPTION_VARIANT_CASING_THE_JOINT = 108;

/*
Common game-option values
*/

const OPTVAL_ENABLED = 1;
const OPTVAL_DISABLED = 2;
