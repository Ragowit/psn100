-- Recreate trophy_earned triggers for MySQL 8.4 deployments.
-- Safe to re-run: drops and recreates all three triggers.
--
-- Changes vs older definitions:
--   * all three triggers honor @psn100_skip_trophy_count so bulk deletes
--     (e.g. DeletePlayerService) can skip per-row player counter updates
--
-- earned never transitions 1→0 (only insert as 0/1, or update 0→1), so
-- after_update only increments on 0→1.
--
-- trophy_earned is ~billions of rows / hundreds of GiB. This script only
-- replaces trigger definitions; it does not ALTER the table or rebuild indexes.

DROP TRIGGER IF EXISTS `after_delete_trophy_earned`;
DROP TRIGGER IF EXISTS `after_insert_trophy_earned`;
DROP TRIGGER IF EXISTS `after_update_trophy_earned`;

DELIMITER $$
CREATE TRIGGER `after_delete_trophy_earned` AFTER DELETE ON `trophy_earned` FOR EACH ROW BEGIN
    IF IFNULL(@psn100_skip_trophy_count, 0) = 0
        AND OLD.earned = 1
        AND OLD.np_communication_id LIKE 'NPWR%' THEN
        UPDATE player SET trophy_count_npwr = trophy_count_npwr - 1 WHERE account_id = OLD.account_id;
    END IF;
END
$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER `after_insert_trophy_earned` AFTER INSERT ON `trophy_earned` FOR EACH ROW BEGIN
    IF IFNULL(@psn100_skip_trophy_count, 0) = 0
        AND NEW.earned = 1
        AND NEW.np_communication_id LIKE 'NPWR%' THEN
        UPDATE player SET trophy_count_npwr = trophy_count_npwr + 1 WHERE account_id = NEW.account_id;
    END IF;
END
$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER `after_update_trophy_earned` AFTER UPDATE ON `trophy_earned` FOR EACH ROW BEGIN
    -- earned only transitions 0→1 (never 1→0); inserts handle the initial value.
    IF IFNULL(@psn100_skip_trophy_count, 0) = 0
        AND OLD.earned = 0
        AND NEW.earned = 1
        AND NEW.np_communication_id LIKE 'NPWR%' THEN
        UPDATE player SET trophy_count_npwr = trophy_count_npwr + 1 WHERE account_id = NEW.account_id;
    END IF;
END
$$
DELIMITER ;
