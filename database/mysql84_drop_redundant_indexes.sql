-- Drop redundant indexes for MySQL 8.4 deployments.
-- Safe on 8.4+; each index is covered by a stricter unique, primary, or composite key.
--
-- Fresh installs already omit these from database/psn100.sql.

ALTER TABLE player
    DROP INDEX player_idx_online_id_account_id;

ALTER TABLE player_ranking
    DROP INDEX idx_pr_account_id_ranking;

ALTER TABLE setting
    DROP INDEX setting_idx_scanning_id;

ALTER TABLE trophy_title_meta
    DROP INDEX idx_ttm_np_id_owners;

ALTER TABLE player_ranking
    DROP INDEX ranking;

ALTER TABLE player_ranking
    DROP INDEX rarity_ranking;
