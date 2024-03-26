--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Reversi implementation : © Gregory Isabelli <gisabelli@boardgamearena.com>
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

-- dbmodel.sql

-- This is the file where your are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- these export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here
--

-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.

-- Example 1: create a standard "card" table to be used with the "Deck" tools (see example game "hearts"):

-- CREATE TABLE IF NOT EXISTS `card` (
--   `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
--   `card_type` varchar(16) NOT NULL,
--   `card_type_arg` int(11) NOT NULL,
--   `card_location` varchar(16) NOT NULL,
--   `card_location_arg` int(11) NOT NULL,
--   PRIMARY KEY (`card_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


-- Example 2: add a custom field to the standard "player" table
-- ALTER TABLE `player` ADD `player_my_custom_field` INT UNSIGNED NOT NULL DEFAULT '0';

ALTER TABLE `player` ADD `player_selection_passed` BOOLEAN NOT NULL DEFAULT FALSE;

CREATE TABLE IF NOT EXISTS `tile` (
  `id` INT(10) unsigned NOT NULL AUTO_INCREMENT,

  `pos_x` INT(1) NOT NULL,
  `pos_y` INT(1) NOT NULL,
  `pos_z` INT(1) NOT NULL,

  `state` ENUM('HIDDEN', 'VISIBLE') DEFAULT 'HIDDEN' NOT NULL,

  `tile_type` VARCHAR(30) NOT NULL,
  `tile_number` INT(1),

  -- The number of counting cubes on the tile (or dice, for tile types
  -- like the Safe and the Owner's Office).
  `counting_cubes` INT(1) DEFAULT 0,
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `wall` (
  `id` INT(10) unsigned NOT NULL AUTO_INCREMENT,

  `pos_x` INT(1) NOT NULL,
  `pos_y` INT(1) NOT NULL,
  `pos_z` INT(1) NOT NULL,

  `vertical` BOOLEAN NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `entity` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,

  `entity_type` ENUM(
    -- N.B.: "Cracked" tokens are not represented as entities.
    'TOKEN_CROWD',
    'TOKEN_OUTOFORDER',
    'TOKEN_DISGUISE',
    'TOKEN_ENTRANCE',
    'TOKEN_ESCALATOR',
    'TOKEN_HANDPRINT',
    'TOKEN_MONORAIL',
    'TOKEN_RAVEN',
    'TOKEN_STEAK',
    'TOKEN_SWAT',

    'CHIP_CROWD',
    'CHIP_DRUNK',
    'CHIP_MOLE',
    'CHIP_PRIMA-DONNA',
    'CHIP_SALESWOMAN',
    'CHIP_UNDERCOVER',

    -- XXX: Perhaps we should split these into "PC" and "NPC" entity types?
    'CHARACTER_PLAYER',
    'CHARACTER_BOUNCER',
    'CHARACTER_TIGER',

    'DESTINATION'
  ) NOT NULL,

  -- If any are NULL, all must be NULL.  This indicates that the
  -- entity is not on the board.
  `pos_x` INT(1),
  `pos_y` INT(1),
  `pos_z` INT(1),

  -- N.B.: The HIDDEN state is only valid for CHIP_* entities, and
  -- indicates that it is face-down.
  `state` ENUM('HIDDEN', 'VISIBLE') NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

-- One row per character; >= 1 character per player.
CREATE TABLE IF NOT EXISTS `character_player` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,

  `entity_id` INT(10) UNSIGNED NOT NULL,

  `state` ENUM('NOT_ENTERED', 'NORMAL', 'ESCAPED') NOT NULL,

  -- Lowest goes first.
  `turn_order` INT(3) NOT NULL,

  `bro` VARCHAR(32) NOT NULL,
  `player_id` INT(10) UNSIGNED NOT NULL,
  `heat` INT(1) NOT NULL,

  `statuses` JSON NOT NULL,

PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `character_npc` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,

  `entity_id` INT(10) UNSIGNED NOT NULL,
  -- Always set when npc_type=="BOUNCER", except when the NPC is in
  -- "hunter" mode (or briefly as bouncers are first being spawned).
  `destination_entity_id` INT(10) UNSIGNED,

  `npc_type` ENUM('BOUNCER', 'TIGER') NOT NULL,

  `statuses` JSON NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `card` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,

  -- These two strings uniquely identify the card-type.
  `card_type_group` VARCHAR(32) NOT NULL,
  `card_type` VARCHAR(32) NOT NULL,

  -- One of "CHARACTER", "GEAR", "PATROL", "DEADDROPS", "LOUNGE", "POOL".
  `card_location` VARCHAR(32) NOT NULL,

  -- One of "DECK", "DISCARD", "HAND", "PREPPED".
  --
  -- The "CHARACTER" location supports the "HAND", "PREPPED", and
  -- "DISCARD" sublocations.
  --
  -- The other locations support the "DECK" and "DISCARD"
  -- sublocations.
  `card_sublocation` VARCHAR(32) NOT NULL,

  -- When `card_location` is "CHARACTER", this is the `characterIndex`.
  -- When `card_location` is "PATROL", this is the Z coordinate (the
  -- zero-indexed floor number).  Otherwise, this must be NULL.
  `card_location_index` INT(1),

  -- The order of the card within the (location, sublocation,
  -- location_index) area.  Lower numbers are "first", or closer to
  -- the top of a deck.
  --
  -- Values should be unique.  When they aren't, behavior is
  -- undefined, though we try to use `id` to break ties.
  `card_order` INT(10) NOT NULL,

  -- The number of times the card has been used.  When this number is
  -- >= the number of uses allowed by the card type, it "flips over":
  -- we show the corresponding gearBack image in the client and its
  -- ability changes to whatever the card's back specifies.  When it
  -- used again in that state, it is discarded.
  --
  -- Must be NULL except for prepped gear cards (cards in the
  -- "PREPPED" sublocation).
  `use_count` INT(1),
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `gamestate` (
  `gamestate_key` VARCHAR(32) NOT NULL,

  -- Exactly one of these columns must be non-NULL.
  `gamestate_value_json` JSON DEFAULT NULL,
  `gamestate_value_int` INT(11) DEFAULT NULL,
PRIMARY KEY (`gamestate_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
