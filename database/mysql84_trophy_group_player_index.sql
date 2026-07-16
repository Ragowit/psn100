-- Optional / scheduled: replace trophy_group_player.idx_account_id with a composite
-- that covers (account_id, np_communication_id) deletes used by scan stale-cleanup.
--
-- Production scale (approx): ~227M rows / ~24 GiB. This is an online InnoDB secondary
-- index build (ALGORITHM=INPLACE, LOCK=NONE when possible) but still hours of IO and
-- temporary disk for the new index before idx_account_id is dropped. Do NOT bundle this
-- with routine post-deploy scripts; run during a maintenance window with disk headroom.
--
-- Fresh installs from database/psn100.sql already have idx_tgp_account_np.
-- Safe to re-run: skips work when the target index already exists / old index is gone.

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
            '), ALGORITHM=INPLACE, LOCK=NONE'
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
            '`, ALGORITHM=INPLACE, LOCK=NONE'
        );
        PREPARE stmt FROM @drop_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CALL psn100_add_index_if_not_exists(
    'trophy_group_player',
    'idx_tgp_account_np',
    '`account_id`, `np_communication_id`'
)$$
CALL psn100_drop_index_if_exists('trophy_group_player', 'idx_account_id')$$

DROP PROCEDURE psn100_add_index_if_not_exists$$
DROP PROCEDURE psn100_drop_index_if_exists$$

DELIMITER ;
