-- phpMyAdmin SQL Dump
-- version 3.4.10.1deb1
-- http://www.phpmyadmin.net
--
-- Värd: 10.209.2.27
-- Skapad: 06 jan 2020 kl 20:58
-- Serverversion: 5.5.52
-- PHP-version: 5.3.10-1ubuntu3.11

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Databas: `psn100`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `player`
--

CREATE TABLE IF NOT EXISTS `player` (
  `account_id` bigint(20) unsigned NOT NULL,
  `online_id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `plus` tinyint(1) NOT NULL,
  `about_me` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_updated_date` datetime NOT NULL,
  `bronze` smallint(5) unsigned NOT NULL,
  `silver` smallint(5) unsigned NOT NULL,
  `gold` smallint(5) unsigned NOT NULL,
  `platinum` smallint(5) unsigned NOT NULL,
  `level` smallint(5) unsigned NOT NULL,
  `progress` tinyint(3) unsigned NOT NULL,
  `points` mediumint(8) unsigned NOT NULL,
  `rarity_points` mediumint(8) unsigned NOT NULL,
  `rank` mediumint(8) unsigned NOT NULL,
  `rank_last_week` mediumint(8) unsigned NOT NULL,
  `rarity_rank` mediumint(8) unsigned NOT NULL,
  `rarity_rank_last_week` mediumint(8) unsigned NOT NULL,
  `rank_country` mediumint(8) unsigned NOT NULL,
  `rank_country_last_week` mediumint(8) unsigned NOT NULL,
  `rarity_rank_country` mediumint(8) unsigned NOT NULL,
  `rarity_rank_country_last_week` mediumint(8) unsigned NOT NULL,
  `common` smallint(5) unsigned NOT NULL,
  `uncommon` smallint(5) unsigned NOT NULL,
  `rare` smallint(5) unsigned NOT NULL,
  `epic` smallint(5) unsigned NOT NULL,
  `legendary` smallint(5) unsigned NOT NULL,
  `status` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `online_id` (`online_id`),
  KEY `points` (`points`,`rarity_points`,`rank`,`rarity_rank`),
  KEY `account_id` (`account_id`,`rank`),
  KEY `rank` (`rank`),
  KEY `rarity_points` (`rarity_points`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur `player_queue`
--

CREATE TABLE IF NOT EXISTS `player_queue` (
  `online_id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `online_id` (`online_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur `setting`
--

CREATE TABLE IF NOT EXISTS `setting` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `refresh_token` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=8 ;

-- --------------------------------------------------------

--
-- Tabellstruktur `trophy`
--

CREATE TABLE IF NOT EXISTS `trophy` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` smallint(5) unsigned NOT NULL,
  `hidden` tinyint(1) NOT NULL,
  `type` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rare` tinyint(3) unsigned NOT NULL,
  `earned_rate` decimal(5,2) unsigned NOT NULL,
  `rarity_percent` decimal(5,2) unsigned NOT NULL,
  `rarity_point` smallint(5) unsigned NOT NULL,
  `status` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `np_communication_id` (`np_communication_id`,`group_id`,`order_id`),
  KEY `np_communication_id_2` (`np_communication_id`),
  KEY `rarity_percent` (`rarity_percent`),
  KEY `np_communication_id_3` (`np_communication_id`,`group_id`,`order_id`,`rarity_point`),
  KEY `np_communication_id_4` (`np_communication_id`,`rarity_percent`),
  KEY `np_communication_id_5` (`np_communication_id`,`group_id`,`order_id`,`rarity_percent`,`rarity_point`,`status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=273950 ;

-- --------------------------------------------------------

--
-- Tabellstruktur `trophy_duplicate`
--

CREATE TABLE IF NOT EXISTS `trophy_duplicate` (
  `child_trophy_id` int(10) unsigned NOT NULL,
  `parent_trophy_id` int(10) unsigned NOT NULL,
  UNIQUE KEY `child_trophy_id` (`child_trophy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur `trophy_earned`
--

CREATE TABLE IF NOT EXISTS `trophy_earned` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` smallint(5) unsigned NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `earned_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `np_communication_id` (`np_communication_id`,`group_id`,`order_id`,`account_id`),
  KEY `np_communication_id_2` (`np_communication_id`,`group_id`,`order_id`),
  KEY `account_id` (`account_id`),
  KEY `np_communication_id_3` (`np_communication_id`),
  KEY `np_communication_id_4` (`np_communication_id`,`earned_date`),
  KEY `earned_date` (`earned_date`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=16785727 ;

-- --------------------------------------------------------

--
-- Tabellstruktur `trophy_group`
--

CREATE TABLE IF NOT EXISTS `trophy_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `bronze` smallint(5) unsigned NOT NULL,
  `silver` smallint(5) unsigned NOT NULL,
  `gold` smallint(5) unsigned NOT NULL,
  `platinum` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `np_communication_id_2` (`np_communication_id`,`group_id`),
  KEY `np_communication_id` (`np_communication_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=11264 ;

-- --------------------------------------------------------

--
-- Tabellstruktur `trophy_group_player`
--

CREATE TABLE IF NOT EXISTS `trophy_group_player` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `bronze` smallint(5) unsigned NOT NULL,
  `silver` smallint(5) unsigned NOT NULL,
  `gold` smallint(5) unsigned NOT NULL,
  `platinum` smallint(5) unsigned NOT NULL,
  `progress` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `np_communication_id` (`np_communication_id`,`group_id`,`account_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1249652 ;

-- --------------------------------------------------------

--
-- Tabellstruktur `trophy_title`
--

CREATE TABLE IF NOT EXISTS `trophy_title` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bronze` smallint(5) unsigned NOT NULL,
  `silver` smallint(5) unsigned NOT NULL,
  `gold` smallint(5) unsigned NOT NULL,
  `platinum` smallint(5) unsigned NOT NULL,
  `owners` int(10) unsigned NOT NULL,
  `difficulty` decimal(5,2) unsigned NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `np_communication_id` (`np_communication_id`),
  KEY `np_communication_id_2` (`np_communication_id`,`status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=9073 ;

-- --------------------------------------------------------

--
-- Tabellstruktur `trophy_title_player`
--

CREATE TABLE IF NOT EXISTS `trophy_title_player` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `bronze` smallint(5) unsigned NOT NULL,
  `silver` smallint(5) unsigned NOT NULL,
  `gold` smallint(5) unsigned NOT NULL,
  `platinum` smallint(5) unsigned NOT NULL,
  `progress` tinyint(3) unsigned NOT NULL,
  `last_updated_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `np_communication_id` (`np_communication_id`,`account_id`),
  KEY `np_communication_id_2` (`np_communication_id`),
  KEY `progress` (`progress`),
  KEY `np_communication_id_3` (`np_communication_id`,`progress`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=722519 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
