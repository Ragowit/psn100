-- Drop redundant indexes for MySQL 8.4 deployments.
-- Safe on 8.4+; each index is covered by a stricter unique, primary, or composite key.
--
-- Idempotent: skips indexes that are already absent, so this script can be re-run on
-- fresh installs from database/psn100.sql or databases that already ran earlier drops.

ALTER TABLE `player` DROP INDEX IF EXISTS `player_idx_online_id_account_id`;
ALTER TABLE `player_ranking` DROP INDEX IF EXISTS `idx_pr_account_id_ranking`;
ALTER TABLE `player_ranking` DROP INDEX IF EXISTS `ranking`;
ALTER TABLE `player_ranking` DROP INDEX IF EXISTS `rarity_ranking`;
ALTER TABLE `player_ranking` DROP INDEX IF EXISTS `in_game_rarity_ranking`;
ALTER TABLE `setting` DROP INDEX IF EXISTS `setting_idx_scanning_id`;
ALTER TABLE `trophy_title_meta` DROP INDEX IF EXISTS `idx_ttm_np_id_owners`;
