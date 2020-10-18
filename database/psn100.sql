-- phpMyAdmin SQL Dump
-- version 4.9.5
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 18, 2020 at 09:02 PM
-- Server version: 8.0.21
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
  `id` int NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player`
--

CREATE TABLE `player` (
  `account_id` bigint UNSIGNED NOT NULL,
  `online_id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_url` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plus` tinyint(1) NOT NULL,
  `about_me` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_updated_date` datetime DEFAULT NULL,
  `bronze` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `silver` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `gold` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `platinum` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `level` smallint UNSIGNED NOT NULL DEFAULT '0',
  `progress` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `points` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `rarity_points` int UNSIGNED NOT NULL DEFAULT '0',
  `rank` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `rank_last_week` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `rarity_rank` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `rarity_rank_last_week` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `rank_country` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `rank_country_last_week` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `rarity_rank_country` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `rarity_rank_country_last_week` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `common` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `uncommon` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `rare` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `epic` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `legendary` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `status` tinyint UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player_queue`
--

CREATE TABLE `player_queue` (
  `online_id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `offset` smallint UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `setting`
--

CREATE TABLE `setting` (
  `id` int UNSIGNED NOT NULL,
  `refresh_token` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy`
--

CREATE TABLE `trophy` (
  `id` int UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` smallint UNSIGNED NOT NULL,
  `hidden` tinyint(1) NOT NULL,
  `type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rare` tinyint UNSIGNED NOT NULL,
  `earned_rate` decimal(5,2) UNSIGNED NOT NULL,
  `rarity_percent` decimal(5,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `rarity_point` smallint UNSIGNED NOT NULL DEFAULT '0',
  `status` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `owners` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_earned`
--

CREATE TABLE `trophy_earned` (
  `id` int UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` smallint UNSIGNED NOT NULL,
  `account_id` bigint UNSIGNED NOT NULL,
  `earned_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_group`
--

CREATE TABLE `trophy_group` (
  `id` int NOT NULL,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bronze` smallint UNSIGNED NOT NULL DEFAULT '0',
  `silver` smallint UNSIGNED NOT NULL DEFAULT '0',
  `gold` smallint UNSIGNED NOT NULL DEFAULT '0',
  `platinum` smallint UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_group_player`
--

CREATE TABLE `trophy_group_player` (
  `id` int UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` bigint UNSIGNED NOT NULL,
  `bronze` smallint UNSIGNED NOT NULL,
  `silver` smallint UNSIGNED NOT NULL,
  `gold` smallint UNSIGNED NOT NULL,
  `platinum` smallint UNSIGNED NOT NULL,
  `progress` tinyint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_merge`
--

CREATE TABLE `trophy_merge` (
  `child_np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `child_group_id` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `child_order_id` smallint UNSIGNED NOT NULL,
  `parent_np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_group_id` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_order_id` smallint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_title`
--

CREATE TABLE `trophy_title` (
  `id` int UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bronze` smallint UNSIGNED NOT NULL DEFAULT '0',
  `silver` smallint UNSIGNED NOT NULL DEFAULT '0',
  `gold` smallint UNSIGNED NOT NULL DEFAULT '0',
  `platinum` smallint UNSIGNED NOT NULL DEFAULT '0',
  `owners` int UNSIGNED NOT NULL DEFAULT '0',
  `difficulty` decimal(5,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `recent_players` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_title_player`
--

CREATE TABLE `trophy_title_player` (
  `id` int UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` bigint UNSIGNED NOT NULL,
  `bronze` smallint UNSIGNED NOT NULL,
  `silver` smallint UNSIGNED NOT NULL,
  `gold` smallint UNSIGNED NOT NULL,
  `platinum` smallint UNSIGNED NOT NULL,
  `progress` tinyint UNSIGNED NOT NULL,
  `last_updated_date` datetime NOT NULL,
  `rarity_points` mediumint UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_merge_icon_url`
-- (See below for the actual view)
--
CREATE TABLE `view_merge_icon_url` (
`icon_url` varchar(36)
,`occurrences` bigint
,`owners` int unsigned
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_merge_name`
-- (See below for the actual view)
--
CREATE TABLE `view_merge_name` (
`name` text
,`occurrences` bigint
,`owners` int unsigned
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_player_last_updated_date`
-- (See below for the actual view)
--
CREATE TABLE `view_player_last_updated_date` (
`account_id` bigint unsigned
,`bronze` mediumint unsigned
,`country` varchar(2)
,`gold` mediumint unsigned
,`last_updated_date` datetime
,`level` smallint unsigned
,`online_id` varchar(16)
,`platinum` mediumint unsigned
,`points` mediumint unsigned
,`progress` tinyint unsigned
,`silver` mediumint unsigned
,`status` tinyint unsigned
);

-- --------------------------------------------------------

--
-- Structure for view `view_merge_icon_url`
--
DROP TABLE IF EXISTS `view_merge_icon_url`;

CREATE ALGORITHM=UNDEFINED DEFINER=`psn100`@`localhost` SQL SECURITY DEFINER VIEW `view_merge_icon_url`  AS  select `trophy_title`.`icon_url` AS `icon_url`,max(`trophy_title`.`owners`) AS `owners`,count(0) AS `occurrences` from `trophy_title` where (`trophy_title`.`status` <> 2) group by `trophy_title`.`icon_url` having (count(0) > 1) order by `owners` desc ;

-- --------------------------------------------------------

--
-- Structure for view `view_merge_name`
--
DROP TABLE IF EXISTS `view_merge_name`;

CREATE ALGORITHM=UNDEFINED DEFINER=`psn100`@`localhost` SQL SECURITY DEFINER VIEW `view_merge_name`  AS  select `trophy_title`.`name` AS `name`,max(`trophy_title`.`owners`) AS `owners`,count(0) AS `occurrences` from `trophy_title` where (`trophy_title`.`status` <> 2) group by `trophy_title`.`name` having (count(0) > 1) order by `owners` desc ;

-- --------------------------------------------------------

--
-- Structure for view `view_player_last_updated_date`
--
DROP TABLE IF EXISTS `view_player_last_updated_date`;

CREATE ALGORITHM=UNDEFINED DEFINER=`psn100`@`localhost` SQL SECURITY DEFINER VIEW `view_player_last_updated_date`  AS  select `player`.`account_id` AS `account_id`,`player`.`online_id` AS `online_id`,`player`.`country` AS `country`,`player`.`last_updated_date` AS `last_updated_date`,`player`.`bronze` AS `bronze`,`player`.`silver` AS `silver`,`player`.`gold` AS `gold`,`player`.`platinum` AS `platinum`,`player`.`level` AS `level`,`player`.`progress` AS `progress`,`player`.`points` AS `points`,`player`.`status` AS `status` from `player` order by -(`player`.`last_updated_date`) ;

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
  ADD KEY `rarity_rank` (`rarity_rank`),
  ADD KEY `account_id` (`account_id`,`rank`,`status`),
  ADD KEY `rank_2` (`rank`,`status`),
  ADD KEY `avatar_url` (`avatar_url`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `player_queue`
--
ALTER TABLE `player_queue`
  ADD UNIQUE KEY `online_id` (`online_id`),
  ADD KEY `request_time` (`request_time`,`online_id`);

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
  ADD KEY `rarity_percent` (`rarity_percent`),
  ADD KEY `np_communication_id_2` (`np_communication_id`,`group_id`,`order_id`,`status`,`rarity_percent`);

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
  ADD UNIQUE KEY `np_communication_id` (`np_communication_id`),
  ADD KEY `np_communication_id_2` (`np_communication_id`,`status`),
  ADD KEY `status` (`status`);
ALTER TABLE `trophy_title` ADD FULLTEXT KEY `name` (`name`);

--
-- Indexes for table `trophy_title_player`
--
ALTER TABLE `trophy_title_player`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `np_communication_id` (`np_communication_id`,`account_id`),
  ADD KEY `progress` (`progress`),
  ADD KEY `np_communication_id_3` (`np_communication_id`,`progress`),
  ADD KEY `fk_p_ttp` (`account_id`),
  ADD KEY `trophy_title_playe_idx_np_id_last_date` (`np_communication_id`,`last_updated_date`),
  ADD KEY `trophy_title_playe_idx_last_date` (`last_updated_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `log`
--
ALTER TABLE `log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `setting`
--
ALTER TABLE `setting`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trophy`
--
ALTER TABLE `trophy`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trophy_earned`
--
ALTER TABLE `trophy_earned`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trophy_group`
--
ALTER TABLE `trophy_group`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trophy_group_player`
--
ALTER TABLE `trophy_group_player`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trophy_title`
--
ALTER TABLE `trophy_title`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trophy_title_player`
--
ALTER TABLE `trophy_title_player`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
