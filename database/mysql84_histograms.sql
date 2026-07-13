-- MySQL 8.4 histogram maintenance for PSN 100%.
-- Run after initial schema import and periodically after large data changes.
-- AUTO UPDATE keeps histograms current when ANALYZE TABLE runs or InnoDB
-- recalculates persistent statistics.
--
-- player_ranking is intentionally omitted: the table is fully rebuilt and
-- swapped every 5 minutes (see PlayerRankingUpdater), so histograms would be
-- stale immediately and AUTO UPDATE would rebuild them on every stats refresh.

ANALYZE TABLE player
    UPDATE HISTOGRAM ON status, last_updated_date, country
    WITH 256 BUCKETS AUTO UPDATE;

ANALYZE TABLE player_queue
    UPDATE HISTOGRAM ON request_time
    WITH 64 BUCKETS AUTO UPDATE;

ANALYZE TABLE trophy_title_player
    UPDATE HISTOGRAM ON progress, last_updated_date
    WITH 256 BUCKETS AUTO UPDATE;

ANALYZE TABLE trophy_title_meta
    UPDATE HISTOGRAM ON status, recent_players, owners
    WITH 256 BUCKETS AUTO UPDATE;

ANALYZE TABLE trophy_title
    UPDATE HISTOGRAM ON platform
    WITH 32 BUCKETS AUTO UPDATE;
