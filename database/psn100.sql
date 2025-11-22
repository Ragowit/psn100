-- phpMyAdmin SQL Dump
-- version 6.0.0-dev+20251026.88b7dfd0f0
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 22, 2025 at 12:30 PM
-- Server version: 8.4.7
-- PHP Version: 8.4.14

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
  `id` bigint UNSIGNED NOT NULL,
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
  `rank_last_week` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `rarity_rank_last_week` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `rank_country_last_week` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `rarity_rank_country_last_week` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `common` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `uncommon` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `rare` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `epic` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `legendary` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `status` tinyint UNSIGNED NOT NULL DEFAULT '99',
  `trophy_count_npwr` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `trophy_count_sony` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `in_game_rarity_points` int UNSIGNED NOT NULL DEFAULT '0',
  `in_game_rarity_rank_last_week` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `in_game_rarity_rank_country_last_week` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `in_game_common` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `in_game_uncommon` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `in_game_rare` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `in_game_epic` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `in_game_legendary` mediumint UNSIGNED NOT NULL DEFAULT '0'
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
-- Table structure for table `player_ranking`
--

CREATE TABLE `player_ranking` (
  `account_id` bigint UNSIGNED NOT NULL,
  `ranking` mediumint UNSIGNED NOT NULL,
  `ranking_country` mediumint UNSIGNED NOT NULL,
  `rarity_ranking` mediumint UNSIGNED NOT NULL,
  `rarity_ranking_country` mediumint UNSIGNED NOT NULL,
  `in_game_rarity_ranking` mediumint UNSIGNED NOT NULL,
  `in_game_rarity_ranking_country` mediumint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player_report`
--

CREATE TABLE `player_report` (
  `report_id` bigint UNSIGNED NOT NULL,
  `account_id` bigint UNSIGNED NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `explanation` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `psn100_avatars`
--

CREATE TABLE `psn100_avatars` (
  `avatar_id` bigint UNSIGNED NOT NULL,
  `size` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `md5_hash` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `extension` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `psn100_change`
--

CREATE TABLE `psn100_change` (
  `id` bigint UNSIGNED NOT NULL,
  `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `change_type` enum('GAME_VERSION','GAME_CLONE','GAME_MERGE','GAME_UPDATE','GAME_DELISTED','GAME_OBSOLETE','GAME_DELISTED_AND_OBSOLETE','GAME_NORMAL','GAME_COPY','GAME_RESET','GAME_DELETE','GAME_RESCAN','GAME_UNOBTAINABLE','GAME_OBTAINABLE','GAME_HISTORY_SNAPSHOT') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `param_1` int NOT NULL,
  `param_2` int DEFAULT NULL,
  `extra` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `setting`
--

CREATE TABLE `setting` (
  `id` bigint UNSIGNED NOT NULL,
  `refresh_token` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `npsso` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `scanning` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `scan_start` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `scan_progress` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy`
--

CREATE TABLE `trophy` (
  `id` bigint UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` smallint UNSIGNED NOT NULL,
  `hidden` tinyint(1) NOT NULL,
  `type` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
PARTITION BY HASH (`account_id`)
PARTITIONS 256;

--
-- Triggers `trophy_earned`
--
DELIMITER $$
CREATE TRIGGER `after_delete_trophy_earned` AFTER DELETE ON `trophy_earned` FOR EACH ROW BEGIN
    IF OLD.earned = 1 AND OLD.np_communication_id LIKE 'NPWR%' THEN
    UPDATE player SET trophy_count_npwr = trophy_count_npwr - 1 WHERE account_id = OLD.account_id;
END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_insert_trophy_earned` AFTER INSERT ON `trophy_earned` FOR EACH ROW BEGIN
    IF NEW.earned = 1 AND NEW.np_communication_id LIKE 'NPWR%' THEN
    UPDATE player SET trophy_count_npwr = trophy_count_npwr + 1 WHERE account_id = NEW.account_id;
END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_update_trophy_earned` AFTER UPDATE ON `trophy_earned` FOR EACH ROW BEGIN
    IF OLD.earned <> NEW.earned AND NEW.np_communication_id LIKE 'NPWR%' THEN
    UPDATE player SET trophy_count_npwr = trophy_count_npwr + 1 WHERE account_id = NEW.account_id;
END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_group`
--

CREATE TABLE `trophy_group` (
  `id` bigint UNSIGNED NOT NULL,
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
-- Table structure for table `trophy_group_history`
--

CREATE TABLE `trophy_group_history` (
  `title_history_id` bigint UNSIGNED NOT NULL,
  `group_id` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `icon_url` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
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
-- Table structure for table `trophy_history`
--

CREATE TABLE `trophy_history` (
  `title_history_id` bigint UNSIGNED NOT NULL,
  `group_id` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` smallint UNSIGNED NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `icon_url` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `progress_target_value` int UNSIGNED DEFAULT NULL
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
-- Table structure for table `trophy_meta`
--

CREATE TABLE `trophy_meta` (
  `trophy_id` bigint UNSIGNED NOT NULL,
  `rarity_percent` decimal(5,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `rarity_point` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `status` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `owners` int UNSIGNED NOT NULL DEFAULT '0',
  `rarity_name` enum('LEGENDARY','EPIC','RARE','UNCOMMON','COMMON','NONE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `in_game_rarity_percent` decimal(5,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `in_game_rarity_point` mediumint UNSIGNED NOT NULL DEFAULT '0',
  `in_game_rarity_name` enum('LEGENDARY','EPIC','RARE','UNCOMMON','COMMON','NONE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NONE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_title`
--

CREATE TABLE `trophy_title` (
  `id` bigint UNSIGNED NOT NULL,
  `np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bronze` smallint UNSIGNED NOT NULL DEFAULT '0',
  `silver` smallint UNSIGNED NOT NULL DEFAULT '0',
  `gold` smallint UNSIGNED NOT NULL DEFAULT '0',
  `platinum` smallint UNSIGNED NOT NULL DEFAULT '0',
  `set_version` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_title_history`
--

CREATE TABLE `trophy_title_history` (
  `id` bigint UNSIGNED NOT NULL,
  `trophy_title_id` bigint UNSIGNED NOT NULL,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `icon_url` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `set_version` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `discovered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trophy_title_meta`
--

CREATE TABLE `trophy_title_meta` (
  `np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owners` int UNSIGNED NOT NULL DEFAULT '0',
  `difficulty` decimal(5,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `recent_players` int UNSIGNED NOT NULL DEFAULT '0',
  `owners_completed` int UNSIGNED NOT NULL DEFAULT '0',
  `psnprofiles_id` int UNSIGNED DEFAULT NULL,
  `parent_np_communication_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `obsolete_ids` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rarity_points` int UNSIGNED NOT NULL DEFAULT '0'
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
  `common` smallint UNSIGNED NOT NULL DEFAULT '0',
  `uncommon` smallint UNSIGNED NOT NULL DEFAULT '0',
  `rare` smallint UNSIGNED NOT NULL DEFAULT '0',
  `epic` smallint UNSIGNED NOT NULL DEFAULT '0',
  `legendary` smallint UNSIGNED NOT NULL DEFAULT '0'
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
  ADD UNIQUE KEY `u_online_id` (`online_id`),
  ADD KEY `idx_avatar_url` (`avatar_url`),
  ADD KEY `idx_last_updated_date` (`last_updated_date`),
  ADD KEY `player_idx_online_id_account_id` (`online_id`,`account_id`),
  ADD KEY `player_idx_status_last_date_online_id` (`status`,`last_updated_date`,`online_id`),
  ADD KEY `idx_trophy_count_npwr` (`trophy_count_npwr`),
  ADD KEY `idx_player_ranking` (`status`,`points` DESC,`platinum` DESC,`gold` DESC,`silver` DESC),
  ADD KEY `player_idx_status_rank_last_week` (`status`,`rank_last_week`),
  ADD KEY `idx_player_status_avatar_account` (`status`,`avatar_url`,`account_id`),
  ADD KEY `idx_player_status_country_account` (`status`,`country`,`account_id`);

--
-- Indexes for table `player_queue`
--
ALTER TABLE `player_queue`
  ADD UNIQUE KEY `u_online_id` (`online_id`),
  ADD KEY `idx_time_oid` (`request_time`,`online_id`),
  ADD KEY `idx_ip_address` (`ip_address`);

--
-- Indexes for table `player_ranking`
--
ALTER TABLE `player_ranking`
  ADD PRIMARY KEY (`account_id`),
  ADD KEY `ranking` (`ranking`),
  ADD KEY `ranking_country` (`ranking_country`),
  ADD KEY `rarity_ranking` (`rarity_ranking`),
  ADD KEY `rarity_ranking_country` (`rarity_ranking_country`),
  ADD KEY `idx_pr_account_id_ranking` (`account_id`,`ranking`),
  ADD KEY `idx_pr_ranking_account` (`ranking`,`account_id`),
  ADD KEY `idx_pr_rarity_ranking_account` (`rarity_ranking`,`account_id`),
  ADD KEY `in_game_rarity_ranking` (`in_game_rarity_ranking`),
  ADD KEY `in_game_rarity_ranking_country` (`in_game_rarity_ranking_country`);

--
-- Indexes for table `player_report`
--
ALTER TABLE `player_report`
  ADD PRIMARY KEY (`report_id`),
  ADD UNIQUE KEY `account_id` (`account_id`,`ip_address`);

--
-- Indexes for table `psn100_avatars`
--
ALTER TABLE `psn100_avatars`
  ADD PRIMARY KEY (`avatar_id`),
  ADD KEY `idx_avatar_url` (`avatar_url`(760));

--
-- Indexes for table `psn100_change`
--
ALTER TABLE `psn100_change`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `setting`
--
ALTER TABLE `setting`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `scanning` (`scanning`),
  ADD KEY `setting_idx_scanning_id` (`scanning`,`id`);

--
-- Indexes for table `trophy`
--
ALTER TABLE `trophy`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_npcid_oid` (`np_communication_id`,`order_id`) USING BTREE,
  ADD KEY `idx_npcid_gid_oid` (`np_communication_id`,`group_id`,`order_id`);

--
-- Indexes for table `trophy_earned`
--
ALTER TABLE `trophy_earned`
  ADD PRIMARY KEY (`np_communication_id`,`order_id`,`account_id`),
  ADD KEY `idx_te_comm_order_earned_acc_date` (`np_communication_id`,`order_id`,`earned`,`account_id`,`earned_date`),
  ADD KEY `idx_te_comm_progress` (`np_communication_id`,`progress`),
  ADD KEY `idx_te_npcomm_order_earned_date` (`np_communication_id`,`order_id`,`earned`,`earned_date`),
  ADD KEY `idx_te_acc_comm_order_earned_date` (`account_id`,`np_communication_id`,`order_id`,`earned`,`earned_date`);

--
-- Indexes for table `trophy_group`
--
ALTER TABLE `trophy_group`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_npcid_gid` (`np_communication_id`,`group_id`);

--
-- Indexes for table `trophy_group_history`
--
ALTER TABLE `trophy_group_history`
  ADD PRIMARY KEY (`title_history_id`,`group_id`),
  ADD KEY `idx_trophy_group_history_group` (`group_id`);

--
-- Indexes for table `trophy_group_player`
--
ALTER TABLE `trophy_group_player`
  ADD PRIMARY KEY (`np_communication_id`,`group_id`,`account_id`) USING BTREE,
  ADD KEY `idx_account_id` (`account_id`);

--
-- Indexes for table `trophy_history`
--
ALTER TABLE `trophy_history`
  ADD PRIMARY KEY (`title_history_id`,`group_id`,`order_id`);

--
-- Indexes for table `trophy_merge`
--
ALTER TABLE `trophy_merge`
  ADD PRIMARY KEY (`child_np_communication_id`,`child_group_id`,`child_order_id`);

--
-- Indexes for table `trophy_meta`
--
ALTER TABLE `trophy_meta`
  ADD PRIMARY KEY (`trophy_id`),
  ADD KEY `idx_tm_status_rarity` (`status`,`rarity_percent`),
  ADD KEY `idx_tm_status_igrp` (`status`,`in_game_rarity_percent`);

--
-- Indexes for table `trophy_title`
--
ALTER TABLE `trophy_title`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_np_communication_id` (`np_communication_id`);
ALTER TABLE `trophy_title` ADD FULLTEXT KEY `idx_name` (`name`);

--
-- Indexes for table `trophy_title_history`
--
ALTER TABLE `trophy_title_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_trophy_title_history_title` (`trophy_title_id`),
  ADD KEY `idx_trophy_title_history_discovered_at` (`discovered_at`);

--
-- Indexes for table `trophy_title_meta`
--
ALTER TABLE `trophy_title_meta`
  ADD PRIMARY KEY (`np_communication_id`),
  ADD KEY `idx_ttm_status` (`status`),
  ADD KEY `idx_ttm_parent_np_communication_id` (`parent_np_communication_id`),
  ADD KEY `idx_ttm_psnprofiles_id` (`psnprofiles_id`),
  ADD KEY `idx_ttm_np_id_owners` (`np_communication_id`,`owners`);

--
-- Indexes for table `trophy_title_player`
--
ALTER TABLE `trophy_title_player`
  ADD PRIMARY KEY (`np_communication_id`,`account_id`) USING BTREE,
  ADD KEY `idx_npcid_progress` (`np_communication_id`,`progress`),
  ADD KEY `idx_npcid_lupdate` (`np_communication_id`,`last_updated_date`),
  ADD KEY `idx_ttp_account_id_progress_updated` (`account_id`,`progress`,`last_updated_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `log`
--
ALTER TABLE `log`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1582344;

--
-- AUTO_INCREMENT for table `player_report`
--
ALTER TABLE `player_report`
  MODIFY `report_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `psn100_avatars`
--
ALTER TABLE `psn100_avatars`
  MODIFY `avatar_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20385;

--
-- AUTO_INCREMENT for table `psn100_change`
--
ALTER TABLE `psn100_change`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50524;

--
-- AUTO_INCREMENT for table `setting`
--
ALTER TABLE `setting`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `trophy`
--
ALTER TABLE `trophy`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2714072;

--
-- AUTO_INCREMENT for table `trophy_group`
--
ALTER TABLE `trophy_group`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85395;

--
-- AUTO_INCREMENT for table `trophy_title`
--
ALTER TABLE `trophy_title`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60286;

--
-- AUTO_INCREMENT for table `trophy_title_history`
--
ALTER TABLE `trophy_title_history`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69765;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `trophy_group_history`
--
ALTER TABLE `trophy_group_history`
  ADD CONSTRAINT `trophy_group_history_title_history_fk` FOREIGN KEY (`title_history_id`) REFERENCES `trophy_title_history` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trophy_history`
--
ALTER TABLE `trophy_history`
  ADD CONSTRAINT `trophy_history_title_history_fk` FOREIGN KEY (`title_history_id`) REFERENCES `trophy_title_history` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trophy_meta`
--
ALTER TABLE `trophy_meta`
  ADD CONSTRAINT `fk_trophy_meta_trophy` FOREIGN KEY (`trophy_id`) REFERENCES `trophy` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trophy_title_history`
--
ALTER TABLE `trophy_title_history`
  ADD CONSTRAINT `trophy_title_history_title_fk` FOREIGN KEY (`trophy_title_id`) REFERENCES `trophy_title` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trophy_title_meta`
--
ALTER TABLE `trophy_title_meta`
  ADD CONSTRAINT `fk_trophy_title_meta_np` FOREIGN KEY (`np_communication_id`) REFERENCES `trophy_title` (`np_communication_id`) ON DELETE CASCADE;
COMMIT;
