CREATE DATABASE `_entity_manager_test` COLLATE 'utf8_general_ci';

USE `_entity_manager_test`;

 -- Database settings for _entity_manager_test:

DROP TABLE IF EXISTS `test_table`;
CREATE TABLE `test_table` (
	`test_table_id` INT(11) NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(256) DEFAULT '', 
	`age` INT(11) DEFAULT NULL,
	`someother_field` VARCHAR(60) DEFAULT 'default value',
	`boolean_field` TINYINT(1) DEFAULT 0,
	PRIMARY KEY (`test_table_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `test_table_relation`;
CREATE TABLE `test_table_relation` (
	`test_table_relation_id` INT(11) NOT NULL AUTO_INCREMENT,
	`test_table_id` INT(11) NOT NULL,
	`relation_field` VARCHAR(64) DEFAULT '',
	PRIMARY KEY (`test_table_relation_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `test_table_another`;
CREATE TABLE `test_table_another` (
	`test_table_another_id` INT(11) NOT NULL AUTO_INCREMENT,
	`some_field` VARCHAR(64) DEFAULT '',
	`boolean_field` TINYINT(1) DEFAULT 1,
	`decimal_field` DECIMAL(10,2) DEFAULT 0.00,
	PRIMARY KEY (`test_table_another_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `test_table_to_test_table_another`;
CREATE TABLE `test_table_to_test_table_another` (
	`test_table_to_test_table_another_id` INT(11) NOT NULL AUTO_INCREMENT,
	`test_table_id` INT(11) NOT NULL,
	`test_table_another_id` INT(11) NOT NULL,
	PRIMARY KEY (`test_table_to_test_table_another_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `circle`;
CREATE TABLE `circle` (
	`circle_id` INT(11) NOT NULL AUTO_INCREMENT,
	`radius` DECIMAL(10,2) NULL DEFAULT 0.00,
	PRIMARY KEY (`circle_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `center`;
CREATE TABLE `center` (
	`center_id` INT(11) NOT NULL AUTO_INCREMENT,
	`circle_id` INT(11) NULL DEFAULT NULL,
	`point` INT(5) NULL DEFAULT 0,
	PRIMARY KEY (`center_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `square`;
CREATE TABLE `square` (
	`square_id` INT(11) NOT NULL AUTO_INCREMENT,
	`cube_id` INT(11) NULL DEFAULT NULL,
	`size` DECIMAL(10,2) NULL DEFAULT 0.00,
	PRIMARY KEY (`square_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `cube`;
CREATE TABLE `cube` (
	`cube_id` INT(11) NOT NULL AUTO_INCREMENT,
	`volume` DECIMAL(5,2) NULL DEFAULT 1.00,
	PRIMARY KEY (`cube_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `sphere`;
CREATE TABLE `sphere` (
	`sphere_id` INT(11) NOT NULL AUTO_INCREMENT,
	`volume` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
	PRIMARY KEY (`sphere_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `sphere_to_circle`;
CREATE TABLE `sphere_to_circle` (
	`sphere_to_circle_id` INT(11) NOT NULL AUTO_INCREMENT,
	`sphere_id` INT(11) NOT NULL,
	`circle_id` INT(11) NOT NULL,
	PRIMARY KEY (`sphere_to_circle_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `my_custom_table`;
CREATE TABLE `my_custom_table` (
	`custom_id` INT(11) NOT NULL AUTO_INCREMENT,
	`field` VARCHAR(127) DEFAULT "uber_default_value",
	PRIMARY KEY (`custom_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `table_with_composite_key`;
CREATE TABLE `table_with_composite_key` (
	`first_key` INT(11) NOT NULL,
	`second_key` INT(11) NOT NULL,
	`field` VARCHAR(127) DEFAULT "value_default_uber",
	PRIMARY KEY (`first_key`, `second_key`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `custom_query`;
CREATE TABLE `custom_query` (
	`custom_query_id` INT(11) NOT NULL AUTO_INCREMENT,
	`field` VARCHAR(128) DEFAULT "",
	PRIMARY KEY (`custom_query_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `time`;
CREATE TABLE `time` (
	`time_id` INT(11) NOT NULL AUTO_INCREMENT,
	`my_timestamp` DATETIME DEFAULT "2015-08-18 16:49:00",
	`my_timestamp_default` TIMESTAMP,
	`my_datetime` DATETIME NOT NULL,
	`my_datetime_default` DATETIME DEFAULT "1900-01-01 00:00:00",
	`my_date` DATE NOT NULL,
	`my_date_default` DATE DEFAULT "1900-01-01",
	PRIMARY KEY (`time_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

DROP TABLE IF EXISTS `item`;
CREATE TABLE `item` (
	`item_id` INT(11) NOT NULL AUTO_INCREMENT,
	`string` VARCHAR(128) NOT NULL,
	`integer` INT(5) NOT NULL,
	`boolean` TINYINT(1) NOT NULL DEFAULT 0,
	`float` FLOAT(5,2) NOT NULL,
	`decimal` DECIMAL(5,2) NOT NULL,
	PRIMARY KEY (`item_id`)
) ENGINE=InnoDB COLLATE='utf8_general_ci';

 --