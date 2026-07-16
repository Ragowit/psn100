-- Drop unused/redundant indexes for MySQL 8.4 deployments.
-- Safe on 8.4+; each index is either covered by a stricter unique/primary/composite
-- key, or unused by application queries (country ranking indexes are display-only
-- columns; idx_trophy_count_npwr is only SELECTed, never filtered/ordered).
--
-- Dropping the three player_ranking country indexes also speeds up the every-5-minute
-- PlayerRankingUpdater rebuild (CREATE TABLE ... LIKE copies secondary indexes).
--
-- Idempotent: skips indexes that are already absent, so this script can be re-run on
-- fresh installs from database/psn100.sql or databases that already ran earlier drops.
--
-- MySQL does not support DROP INDEX IF EXISTS in ALTER TABLE, so small procedures
-- check information_schema before issuing each ADD or DROP.

DELIMITER $$

DROP PROCEDURE IF EXISTS psn100_add_index_if_not_exists$$
CREATE PROCEDURE psn100_add_index_if_not_exists(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_index_columns VARCHAR(255)
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

CALL psn100_add_index_if_not_exists(
    'player_ranking',
    'idx_pr_in_game_rarity_ranking_account',
    '`in_game_rarity_ranking`, `account_id`'
)$$

CALL psn100_drop_index_if_exists('player', 'player_idx_online_id_account_id')$$
CALL psn100_drop_index_if_exists('player', 'idx_trophy_count_npwr')$$
CALL psn100_drop_index_if_exists('player_ranking', 'idx_pr_account_id_ranking')$$
CALL psn100_drop_index_if_exists('player_ranking', 'ranking')$$
CALL psn100_drop_index_if_exists('player_ranking', 'rarity_ranking')$$
CALL psn100_drop_index_if_exists('player_ranking', 'in_game_rarity_ranking')$$
CALL psn100_drop_index_if_exists('player_ranking', 'ranking_country')$$
CALL psn100_drop_index_if_exists('player_ranking', 'rarity_ranking_country')$$
CALL psn100_drop_index_if_exists('player_ranking', 'in_game_rarity_ranking_country')$$
CALL psn100_drop_index_if_exists('setting', 'setting_idx_scanning_id')$$
CALL psn100_drop_index_if_exists('trophy_title_meta', 'idx_ttm_np_id_owners')$$

DROP PROCEDURE psn100_add_index_if_not_exists$$
DROP PROCEDURE psn100_drop_index_if_exists$$

DELIMITER ;

-- Enforce trophy.type domain on upgraded databases (idempotent).
SET @chk_trophy_type_exists := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'trophy'
      AND constraint_name = 'chk_trophy_type'
      AND constraint_type = 'CHECK'
);

SET @chk_trophy_type_sql := IF(
    @chk_trophy_type_exists = 0,
    'ALTER TABLE `trophy` ADD CONSTRAINT `chk_trophy_type` CHECK (`type` IN (''bronze'', ''silver'', ''gold'', ''platinum''))',
    'DO 0'
);

PREPARE chk_trophy_type_stmt FROM @chk_trophy_type_sql;
EXECUTE chk_trophy_type_stmt;
DEALLOCATE PREPARE chk_trophy_type_stmt;
