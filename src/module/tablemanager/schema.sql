DROP TABLE IF EXISTS `table`;

CREATE TABLE `table` (
  `table_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_game` VARCHAR(32) NOT NULL,
  `table_database` VARCHAR(64),
  PRIMARY KEY (`table_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
