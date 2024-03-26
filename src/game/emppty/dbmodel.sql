
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Chakra implementation : © Nicolas Gocel <nicolas.gocel@gmail.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

ALTER TABLE `player` ADD `purple` int(5) NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `darkblue` int(5) NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `blue` int(5) NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `green` int(5) NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `yellow` int(5) NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `orange` int(5) NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `red` int(5) NOT NULL DEFAULT '0';


CREATE TABLE IF NOT EXISTS `plenitude` (
   `color` varchar(16) NOT NULL,
   `value` int(5) unsigned NOT NULL,
    PRIMARY KEY (`color`)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
  
CREATE TABLE IF NOT EXISTS `inspiration` (
   `player_id` int(10) NOT NULL,
   `id` int(10) NOT NULL,
   `location` varchar(16) NOT NULL,
   `location_arg` int(3) NULL,
    PRIMARY KEY (`player_id`,`id`)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
 
 
CREATE TABLE IF NOT EXISTS `energy` (
   `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
   `color` varchar(16) NOT NULL,
   `location` varchar(16) NOT NULL,
   `row` int(3) NULL,
   `col` int(3) NULL,
   PRIMARY KEY (`id`)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;