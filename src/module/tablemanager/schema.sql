DROP TABLE IF EXISTS `table`;

CREATE TABLE `table` (
  `table_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_game` VARCHAR(32) NOT NULL,
  `table_database` VARCHAR(64),
  `table_legacy_scope` VARCHAR(64),
  PRIMARY KEY (`table_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Legacy-games ("campaign") data; see module/table/LocalArenaLegacy.php.
-- Per-player and team state have different keys, so they live in
-- separate tables.  The key/value columns are named data_key and
-- data_value because KEY is a MySQL reserved word.

DROP TABLE IF EXISTS `legacy_player_data`;

CREATE TABLE `legacy_player_data` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` VARCHAR(64) NOT NULL COMMENT 'empty: the shared pool of the game (tests use private scopes)',
  `game_name` VARCHAR(32) NOT NULL,
  `player_id` INT(10) UNSIGNED NOT NULL COMMENT '0: data global to the game',
  `data_key` VARCHAR(64) NOT NULL,
  `data_value` MEDIUMTEXT NOT NULL,
  `expiration` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entry` (`scope`, `game_name`, `player_id`, `data_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `legacy_team_data`;

CREATE TABLE `legacy_team_data` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` VARCHAR(64) NOT NULL COMMENT 'empty: the shared pool of the game (tests use private scopes)',
  `game_name` VARCHAR(32) NOT NULL,
  `team` VARCHAR(191) NOT NULL COMMENT 'team signature: sorted player ids joined with commas',
  `data_value` MEDIUMTEXT NOT NULL,
  `expiration` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entry` (`scope`, `game_name`, `team`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Allocator backing unique legacy scopes for the test harness (see
-- IntegrationTestCase::legacyScope()).

DROP TABLE IF EXISTS `legacy_scope_alloc`;

CREATE TABLE `legacy_scope_alloc` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
