-- Covering/sort indexes and status CHECK constraints for MySQL 8.4 deployments.
-- Safe to re-run: adds missing indexes/constraints and drops superseded ones.
--
-- Hot paths covered:
--   * Game leaderboard ORDER BY progress/platinum/gold/silver/bronze/date
--   * Homepage popular games ORDER BY recent_players
--   * Game list ORDER BY difficulty/owners/rarity/in-game rarity
--
-- idx_npcid_progress is replaced by the wider leaderboard index (same leading
-- columns). idx_ttm_status is replaced by composites that keep status as the
-- leftmost prefix.

DELIMITER $$

DROP PROCEDURE IF EXISTS psn100_add_index_if_not_exists$$
CREATE PROCEDURE psn100_add_index_if_not_exists(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_index_columns VARCHAR(512)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = p_table_name
          AND index_name = p_index_name
    ) THEN
        SET @add_sql = CONCAT(
            'ALTER TABLE `',
            REPLACE(p_table_name, '`', '``'),
            '` ADD INDEX `',
            REPLACE(p_index_name, '`', '``'),
            '` (',
            p_index_columns,
            ')'
        );
        PREPARE stmt FROM @add_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS psn100_drop_index_if_exists$$
CREATE PROCEDURE psn100_drop_index_if_exists(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = p_table_name
          AND index_name = p_index_name
    ) THEN
        SET @drop_sql = CONCAT(
            'ALTER TABLE `',
            REPLACE(p_table_name, '`', '``'),
            '` DROP INDEX `',
            REPLACE(p_index_name, '`', '``'),
            '`'
        );
        PREPARE stmt FROM @drop_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS psn100_add_check_if_not_exists$$
CREATE PROCEDURE psn100_add_check_if_not_exists(
    IN p_table_name VARCHAR(64),
    IN p_constraint_name VARCHAR(64),
    IN p_check_clause VARCHAR(512)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.table_constraints
        WHERE table_schema = DATABASE()
          AND table_name = p_table_name
          AND constraint_name = p_constraint_name
          AND constraint_type = 'CHECK'
    ) THEN
        SET @add_sql = CONCAT(
            'ALTER TABLE `',
            REPLACE(p_table_name, '`', '``'),
            '` ADD CONSTRAINT `',
            REPLACE(p_constraint_name, '`', '``'),
            '` CHECK (',
            p_check_clause,
            ')'
        );
        PREPARE stmt FROM @add_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS psn100_drop_check_if_exists$$
CREATE PROCEDURE psn100_drop_check_if_exists(
    IN p_table_name VARCHAR(64),
    IN p_constraint_name VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.table_constraints
        WHERE table_schema = DATABASE()
          AND table_name = p_table_name
          AND constraint_name = p_constraint_name
          AND constraint_type = 'CHECK'
    ) THEN
        SET @drop_sql = CONCAT(
            'ALTER TABLE `',
            REPLACE(p_table_name, '`', '``'),
            '` DROP CHECK `',
            REPLACE(p_constraint_name, '`', '``'),
            '`'
        );
        PREPARE stmt FROM @drop_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CALL psn100_add_index_if_not_exists(
    'trophy_title_player',
    'idx_ttp_leaderboard',
    '`np_communication_id`, `progress` DESC, `platinum` DESC, `gold` DESC, `silver` DESC, `bronze` DESC, `last_updated_date`'
)$$
CALL psn100_drop_index_if_exists('trophy_title_player', 'idx_npcid_progress')$$

CALL psn100_add_index_if_not_exists(
    'trophy_title_meta',
    'idx_ttm_status_recent_players',
    '`status`, `recent_players` DESC'
)$$
CALL psn100_add_index_if_not_exists(
    'trophy_title_meta',
    'idx_ttm_status_difficulty_owners',
    '`status`, `difficulty` DESC, `owners` DESC'
)$$
CALL psn100_add_index_if_not_exists(
    'trophy_title_meta',
    'idx_ttm_status_owners',
    '`status`, `owners` DESC'
)$$
CALL psn100_add_index_if_not_exists(
    'trophy_title_meta',
    'idx_ttm_status_rarity_owners',
    '`status`, `rarity_points` DESC, `owners` DESC'
)$$
CALL psn100_add_index_if_not_exists(
    'trophy_title_meta',
    'idx_ttm_status_igrp_owners',
    '`status`, `in_game_rarity_points` DESC, `owners` DESC'
)$$
CALL psn100_drop_index_if_exists('trophy_title_meta', 'idx_ttm_status')$$

CALL psn100_add_check_if_not_exists(
    'player',
    'chk_player_status',
    '`status` IN (0, 1, 3, 4, 5, 99)'
)$$
-- Recreate so a previously-narrow chk_ttm_status (0,1,2) is replaced.
-- GameAvailabilityStatus also uses 3 (OBSOLETE) and 4 (DELISTED_AND_OBSOLETE).
CALL psn100_drop_check_if_exists('trophy_title_meta', 'chk_ttm_status')$$
CALL psn100_add_check_if_not_exists(
    'trophy_title_meta',
    'chk_ttm_status',
    '`status` IN (0, 1, 2, 3, 4)'
)$$
CALL psn100_add_check_if_not_exists(
    'trophy_meta',
    'chk_tm_status',
    '`status` IN (0, 1)'
)$$

DROP PROCEDURE psn100_add_index_if_not_exists$$
DROP PROCEDURE psn100_drop_index_if_exists$$
DROP PROCEDURE psn100_add_check_if_not_exists$$
DROP PROCEDURE psn100_drop_check_if_exists$$

DELIMITER ;

-- Prefer JSON for scan_progress payloads written by WorkerScanCoordinator.
-- Null non-JSON legacy/corrupt values first: MODIFY ... JSON rejects invalid rows,
-- and app readers already treat malformed scan_progress as null.
SET @scan_progress_sanitize := (
    SELECT IF(
        DATA_TYPE = 'json',
        'DO 0',
        'UPDATE `setting` SET `scan_progress` = NULL WHERE `scan_progress` IS NOT NULL AND JSON_VALID(`scan_progress`) = 0'
    )
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'setting'
      AND column_name = 'scan_progress'
);

PREPARE scan_progress_sanitize_stmt FROM @scan_progress_sanitize;
EXECUTE scan_progress_sanitize_stmt;
DEALLOCATE PREPARE scan_progress_sanitize_stmt;

SET @scan_progress_json := (
    SELECT IF(
        DATA_TYPE = 'json',
        'DO 0',
        'ALTER TABLE `setting` MODIFY `scan_progress` JSON'
    )
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'setting'
      AND column_name = 'scan_progress'
);

PREPARE scan_progress_stmt FROM @scan_progress_json;
EXECUTE scan_progress_stmt;
DEALLOCATE PREPARE scan_progress_stmt;
