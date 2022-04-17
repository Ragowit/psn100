-- phpMyAdmin SQL Dump
-- version 5.2.0-rc1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 17, 2022 at 06:00 PM
-- Server version: 8.0.28
-- PHP Version: 8.1.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

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
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player`
--

CREATE TABLE `player` (
  `account_id` bigint UNSIGNED NOT NULL,
  `online_id` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_url` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plus` tinyint(1) NOT NULL,
  `about_me` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `status` tinyint UNSIGNED NOT NULL DEFAULT '99'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player_queue`
--

CREATE TABLE `player_queue` (
  `online_id` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `offset` smallint UNSIGNED NOT NULL DEFAULT '0',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `psn100_avatars`
--

CREATE TABLE `psn100_avatars` (
  `avatar_id` int UNSIGNED NOT NULL,
  `size` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `md5_hash` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `extension` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `setting`
--

CREATE TABLE `setting` (
  `id` int UNSIGNED NOT NULL,
  `refresh_token` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `npsso` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy`
--

CREATE TABLE `trophy` (
  `id` int UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` smallint UNSIGNED NOT NULL,
  `hidden` tinyint(1) NOT NULL,
  `type` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rarity_percent` decimal(5,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `rarity_point` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `status` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `owners` int UNSIGNED NOT NULL DEFAULT '0',
  `rarity_name` enum('LEGENDARY','EPIC','RARE','UNCOMMON','COMMON','NONE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `progress_target_value` int UNSIGNED DEFAULT NULL,
  `reward_name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reward_image_url` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_earned`
--

CREATE TABLE `trophy_earned` (
  `np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` smallint UNSIGNED NOT NULL,
  `account_id` bigint UNSIGNED NOT NULL,
  `earned_date` datetime DEFAULT NULL,
  `progress` int UNSIGNED DEFAULT NULL,
  `earned` tinyint NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY KEY (np_communication_id)
(
PARTITION p0 ENGINE=InnoDB,
PARTITION p1 ENGINE=InnoDB,
PARTITION p2 ENGINE=InnoDB,
PARTITION p3 ENGINE=InnoDB,
PARTITION p4 ENGINE=InnoDB,
PARTITION p5 ENGINE=InnoDB,
PARTITION p6 ENGINE=InnoDB,
PARTITION p7 ENGINE=InnoDB,
PARTITION p8 ENGINE=InnoDB,
PARTITION p9 ENGINE=InnoDB,
PARTITION p10 ENGINE=InnoDB,
PARTITION p11 ENGINE=InnoDB,
PARTITION p12 ENGINE=InnoDB,
PARTITION p13 ENGINE=InnoDB,
PARTITION p14 ENGINE=InnoDB,
PARTITION p15 ENGINE=InnoDB,
PARTITION p16 ENGINE=InnoDB,
PARTITION p17 ENGINE=InnoDB,
PARTITION p18 ENGINE=InnoDB,
PARTITION p19 ENGINE=InnoDB,
PARTITION p20 ENGINE=InnoDB,
PARTITION p21 ENGINE=InnoDB,
PARTITION p22 ENGINE=InnoDB,
PARTITION p23 ENGINE=InnoDB,
PARTITION p24 ENGINE=InnoDB
);

-- --------------------------------------------------------

--
-- Table structure for table `trophy_group`
--

CREATE TABLE `trophy_group` (
  `id` int NOT NULL,
  `np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `child_np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `child_group_id` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `child_order_id` smallint UNSIGNED NOT NULL,
  `parent_np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_group_id` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_order_id` smallint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_title`
--

CREATE TABLE `trophy_title` (
  `id` int UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bronze` smallint UNSIGNED NOT NULL DEFAULT '0',
  `silver` smallint UNSIGNED NOT NULL DEFAULT '0',
  `gold` smallint UNSIGNED NOT NULL DEFAULT '0',
  `platinum` smallint UNSIGNED NOT NULL DEFAULT '0',
  `owners` int UNSIGNED NOT NULL DEFAULT '0',
  `difficulty` decimal(5,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `recent_players` int UNSIGNED NOT NULL DEFAULT '0',
  `set_version` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_title_player`
--

CREATE TABLE `trophy_title_player` (
  `np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` bigint UNSIGNED NOT NULL,
  `bronze` smallint UNSIGNED NOT NULL,
  `silver` smallint UNSIGNED NOT NULL,
  `gold` smallint UNSIGNED NOT NULL,
  `platinum` smallint UNSIGNED NOT NULL,
  `progress` tinyint UNSIGNED NOT NULL,
  `last_updated_date` datetime NOT NULL,
  `rarity_points` int UNSIGNED NOT NULL DEFAULT '0',
  `temp_rarity_points` int UNSIGNED NOT NULL DEFAULT '0',
  `common` smallint UNSIGNED NOT NULL DEFAULT '0',
  `uncommon` smallint UNSIGNED NOT NULL DEFAULT '0',
  `rare` smallint UNSIGNED NOT NULL DEFAULT '0',
  `epic` smallint UNSIGNED NOT NULL DEFAULT '0',
  `legendary` smallint UNSIGNED NOT NULL DEFAULT '0',
  `temp_common` smallint UNSIGNED NOT NULL DEFAULT '0',
  `temp_uncommon` smallint UNSIGNED NOT NULL DEFAULT '0',
  `temp_rare` smallint UNSIGNED NOT NULL DEFAULT '0',
  `temp_epic` smallint UNSIGNED NOT NULL DEFAULT '0',
  `temp_legendary` smallint UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Structure for view `view_player_last_updated_date`
--
DROP TABLE IF EXISTS `view_player_last_updated_date`;

CREATE ALGORITHM=UNDEFINED DEFINER=`psn100_ragowit`@`localhost` SQL SECURITY DEFINER VIEW `view_player_last_updated_date`  AS SELECT `player`.`account_id` AS `account_id`, `player`.`online_id` AS `online_id`, `player`.`country` AS `country`, `player`.`last_updated_date` AS `last_updated_date`, `player`.`bronze` AS `bronze`, `player`.`silver` AS `silver`, `player`.`gold` AS `gold`, `player`.`platinum` AS `platinum`, `player`.`level` AS `level`, `player`.`progress` AS `progress`, `player`.`points` AS `points`, `player`.`status` AS `status` FROM `player` ORDER BY -(`player`.`last_updated_date`) ASC  ;

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
  ADD UNIQUE KEY `u_online_id` (`online_id`),
  ADD KEY `idx_rarity_rank` (`rarity_rank`),
  ADD KEY `idx_aid_rank_status` (`account_id`,`rank`,`status`),
  ADD KEY `idx_rank_status` (`rank`,`status`),
  ADD KEY `idx_avatar_url` (`avatar_url`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `player_queue`
--
ALTER TABLE `player_queue`
  ADD UNIQUE KEY `u_online_id` (`online_id`),
  ADD KEY `idx_time_oid` (`request_time`,`online_id`);

--
-- Indexes for table `psn100_avatars`
--
ALTER TABLE `psn100_avatars`
  ADD PRIMARY KEY (`avatar_id`);

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
  ADD UNIQUE KEY `u_npcid_gid_oid` (`np_communication_id`,`group_id`,`order_id`),
  ADD KEY `idx_rarity_percent` (`rarity_percent`),
  ADD KEY `idx_npcid_gid_oid_status_rarpercent` (`np_communication_id`,`group_id`,`order_id`,`status`,`rarity_percent`),
  ADD KEY `idx_status_npcid_rarname` (`status`,`np_communication_id`,`rarity_name`),
  ADD KEY `idx_npcid_gid_oid_rarpoint` (`np_communication_id`,`group_id`,`order_id`,`rarity_point`),
  ADD KEY `idx_rarity_point` (`rarity_point`);

--
-- Indexes for table `trophy_earned`
--
ALTER TABLE `trophy_earned`
  ADD PRIMARY KEY (`np_communication_id`,`group_id`,`order_id`,`account_id`),
  ADD KEY `idx_account_id` (`account_id`);

--
-- Indexes for table `trophy_group`
--
ALTER TABLE `trophy_group`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_npcid_gid` (`np_communication_id`,`group_id`);

--
-- Indexes for table `trophy_group_player`
--
ALTER TABLE `trophy_group_player`
  ADD PRIMARY KEY (`np_communication_id`,`group_id`,`account_id`) USING BTREE,
  ADD KEY `idx_account_id` (`account_id`);

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
  ADD UNIQUE KEY `u_np_communication_id` (`np_communication_id`),
  ADD KEY `idx_npcid_status` (`np_communication_id`,`status`),
  ADD KEY `idx_status` (`status`);
ALTER TABLE `trophy_title` ADD FULLTEXT KEY `idx_name` (`name`);

--
-- Indexes for table `trophy_title_player`
--
ALTER TABLE `trophy_title_player`
  ADD PRIMARY KEY (`np_communication_id`,`account_id`) USING BTREE,
  ADD KEY `idx_progress` (`progress`),
  ADD KEY `idx_npcid_progress` (`np_communication_id`,`progress`),
  ADD KEY `idx_account_id` (`account_id`),
  ADD KEY `idx_npcid_lupdate` (`np_communication_id`,`last_updated_date`),
  ADD KEY `idx_last_updated_date` (`last_updated_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `log`
--
ALTER TABLE `log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `psn100_avatars`
--
ALTER TABLE `psn100_avatars`
  MODIFY `avatar_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `trophy_group`
--
ALTER TABLE `trophy_group`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trophy_title`
--
ALTER TABLE `trophy_title`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;
