DROP DATABASE `babawoo`;
CREATE DATABASE `babawoo`;
USE `babawoo`;

CREATE TABLE IF NOT EXISTS `config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(15) NOT NULL,
  `slug` varchar(15) NOT NULL,
  `line_id` varchar(15) NOT NULL,
  `region` varchar(15) NOT NULL,
  `direction` tinyint(4) NOT NULL,
  `origin_stop_id` int(11) DEFAULT NULL,
  `terminal_stop_id` int(11) DEFAULT NULL,
  `first_vehicle_hour` time NULL DEFAULT NULL,
  `last_vehicle_hour` time NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug-direction` (`slug`,`direction`),
  KEY `name` (`name`),
  KEY `origin_stop_id` (`origin_stop_id`),
  KEY `terminal_stop_id` (`terminal_stop_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `line_stop` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `line_id` int(11) NOT NULL,
  `stop_id` int(11) NOT NULL,
  `stop_no` int(11) DEFAULT NULL,
  `first_vehicle_hour` time NULL DEFAULT NULL,
  `last_vehicle_hour` time NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `stop_id` (`stop_id`),
  KEY `stop_no` (`stop_no`),
  UNIQUE KEY `line_id-stop_id-stop_no` (`line_id`,`stop_id`, `stop_no`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(31) NOT NULL,
  `event` varchar(31) NOT NULL,
  `meta` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `stops` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `latitude` (`latitude`),
  KEY `longitude` (`longitude`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `openid` varchar(255) NOT NULL,
  `meta` text DEFAULT NULL,
  `favorite` text DEFAULT NULL,
  `session` text DEFAULT NULL,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL,
  `precision` smallint(4) NOT NULL,
  `last_active_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  UNIQUE KEY `openid` (`openid`),
  KEY `last_active_at` (`last_active_at`),
  KEY `latitude` (`latitude`),
  KEY `longitude` (`longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `line_stop`
  ADD CONSTRAINT `line_stop_ibfk_1` FOREIGN KEY (`line_id`) REFERENCES `lines` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  ADD CONSTRAINT `line_stop_ibfk_2` FOREIGN KEY (`stop_id`) REFERENCES `stops` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE;

ALTER TABLE `lines`
  ADD CONSTRAINT `lines_ibfk_1` FOREIGN KEY (`origin_stop_id`) REFERENCES `stops` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  ADD CONSTRAINT `lines_ibfk_2` FOREIGN KEY (`terminal_stop_id`) REFERENCES `stops` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE;

ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE;


