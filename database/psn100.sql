-- phpMyAdmin SQL Dump
-- version 4.9.4
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 06, 2020 at 10:11 PM
-- Server version: 5.7.29
-- PHP Version: 7.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `psn100`
--

-- --------------------------------------------------------

--
-- Table structure for table `log`
--

CREATE TABLE `log` (
  `id` int(11) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player`
--

CREATE TABLE `player` (
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `online_id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `plus` tinyint(1) NOT NULL,
  `about_me` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_updated_date` datetime DEFAULT NULL,
  `bronze` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `silver` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `gold` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `platinum` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `level` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `progress` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `points` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `rarity_points` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `rank` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `rank_last_week` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `rarity_rank` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `rarity_rank_last_week` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `rank_country` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `rank_country_last_week` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `rarity_rank_country` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `rarity_rank_country_last_week` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `common` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `uncommon` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `rare` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `epic` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `legendary` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `private_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player_queue`
--

CREATE TABLE `player_queue` (
  `online_id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `offset` smallint(5) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `setting`
--

CREATE TABLE `setting` (
  `id` int(11) UNSIGNED NOT NULL,
  `refresh_token` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy`
--

CREATE TABLE `trophy` (
  `id` int(10) UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` smallint(5) UNSIGNED NOT NULL,
  `hidden` tinyint(1) NOT NULL,
  `type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `rare` tinyint(3) UNSIGNED NOT NULL,
  `earned_rate` decimal(5,2) UNSIGNED NOT NULL,
  `rarity_percent` decimal(5,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `rarity_point` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `owners` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_earned`
--

CREATE TABLE `trophy_earned` (
  `id` int(11) UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` smallint(5) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `earned_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_group`
--

CREATE TABLE `trophy_group` (
  `id` int(11) NOT NULL,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `bronze` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `silver` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `gold` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `platinum` smallint(5) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_group_player`
--

CREATE TABLE `trophy_group_player` (
  `id` int(10) UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `bronze` smallint(5) UNSIGNED NOT NULL,
  `silver` smallint(5) UNSIGNED NOT NULL,
  `gold` smallint(5) UNSIGNED NOT NULL,
  `platinum` smallint(5) UNSIGNED NOT NULL,
  `progress` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_merge`
--

CREATE TABLE `trophy_merge` (
  `child_np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `child_group_id` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `child_order_id` smallint(5) UNSIGNED NOT NULL,
  `parent_np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_group_id` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_order_id` smallint(5) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_title`
--

CREATE TABLE `trophy_title` (
  `id` int(10) UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bronze` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `silver` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `gold` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `platinum` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `owners` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `difficulty` decimal(5,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `recent_players` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_title_player`
--

CREATE TABLE `trophy_title_player` (
  `id` int(10) UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `bronze` smallint(5) UNSIGNED NOT NULL,
  `silver` smallint(5) UNSIGNED NOT NULL,
  `gold` smallint(5) UNSIGNED NOT NULL,
  `platinum` smallint(5) UNSIGNED NOT NULL,
  `progress` tinyint(3) UNSIGNED NOT NULL,
  `last_updated_date` datetime NOT NULL,
  `rarity_points` mediumint(8) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `log`
--
ALTER TABLE `log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `player`
--
ALTER TABLE `player`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `online_id` (`online_id`),
  ADD KEY `rank` (`rank`),
  ADD KEY `rarity_rank` (`rarity_rank`);

--
-- Indexes for table `player_queue`
--
ALTER TABLE `player_queue`
  ADD UNIQUE KEY `online_id` (`online_id`);

--
-- Indexes for table `setting`
--
ALTER TABLE `setting`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trophy`
--
ALTER TABLE `trophy`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `np_communication_id` (`np_communication_id`,`group_id`,`order_id`),
  ADD KEY `rarity_percent` (`rarity_percent`);

--
-- Indexes for table `trophy_earned`
--
ALTER TABLE `trophy_earned`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `np_communication_id` (`np_communication_id`,`group_id`,`order_id`,`account_id`),
  ADD KEY `fk_p_te` (`account_id`),
  ADD KEY `np_communication_id_5` (`np_communication_id`,`account_id`,`earned_date`);

--
-- Indexes for table `trophy_group`
--
ALTER TABLE `trophy_group`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `np_communication_id_2` (`np_communication_id`,`group_id`);

--
-- Indexes for table `trophy_group_player`
--
ALTER TABLE `trophy_group_player`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `np_communication_id` (`np_communication_id`,`group_id`,`account_id`),
  ADD KEY `fk_p_tgp` (`account_id`);

--
-- Indexes for table `trophy_merge`
--
ALTER TABLE `trophy_merge`
  ADD PRIMARY KEY (`child_np_communication_id`,`child_group_id`,`child_order_id`);

--
-- Indexes for table `trophy_title`
--
ALTER TABLE `trophy_title`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `np_communication_id` (`np_communication_id`);
ALTER TABLE `trophy_title` ADD FULLTEXT KEY `name` (`name`);

--
-- Indexes for table `trophy_title_player`
--
ALTER TABLE `trophy_title_player`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `np_communication_id` (`np_communication_id`,`account_id`),
  ADD KEY `progress` (`progress`),
  ADD KEY `np_communication_id_3` (`np_communication_id`,`progress`),
  ADD KEY `fk_p_ttp` (`account_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `log`
--
ALTER TABLE `log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `setting`
--
ALTER TABLE `setting`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trophy`
--
ALTER TABLE `trophy`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trophy_earned`
--
ALTER TABLE `trophy_earned`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trophy_group`
--
ALTER TABLE `trophy_group`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trophy_group_player`
--
ALTER TABLE `trophy_group_player`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trophy_title`
--
ALTER TABLE `trophy_title`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trophy_title_player`
--
ALTER TABLE `trophy_title_player`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
