-- Safe trophy_earned index rollout for MySQL 8.4
-- Step 1: Create replacement indexes first.
ALTER TABLE trophy_earned
    ADD INDEX idx_te_acc_earned_np_date (account_id, earned, np_communication_id, earned_date),
    ALGORITHM=INPLACE,
    LOCK=NONE;

-- Step 2: Compare query plans and costs against production-like sampling.
-- 2a) Trophy achievers lookup (TrophyService::getAchievers)
EXPLAIN ANALYZE
SELECT
    te.account_id,
    te.earned_date
FROM trophy_earned te
WHERE te.np_communication_id = 'NPWR00000_00'
  AND te.order_id = 1
  AND te.earned = 1
ORDER BY te.earned_date
LIMIT 50;

-- 2b) Player completion label aggregation (PlayerGamesService::fetchCompletionLabels)
EXPLAIN ANALYZE
SELECT np_communication_id, MIN(earned_date) AS first_trophy, MAX(earned_date) AS last_trophy
FROM trophy_earned
WHERE account_id = 1
  AND earned = 1
  AND np_communication_id IN ('NPWR00000_00', 'NPWR00001_00', 'NPWR00002_00')
GROUP BY np_communication_id
HAVING MIN(earned_date) <> MAX(earned_date);

-- 2c) Account-scoped trophy listing CTE source (GameService::getTrophies)
EXPLAIN ANALYZE
SELECT np_communication_id, group_id, order_id, earned_date, progress, earned
FROM trophy_earned
WHERE account_id = 1;

-- 2d) Write-heavy upsert path key probe (ThirtyMinuteCronJob/TrophyMergeService)
EXPLAIN ANALYZE
SELECT earned_date, progress, earned
FROM trophy_earned
WHERE np_communication_id = 'NPWR00000_00'
  AND order_id = 1
  AND account_id = 1;

-- Step 3: Drop only confirmed-redundant indexes.
-- Drop these only after Step 2 confirms no regressions:
ALTER TABLE trophy_earned
    DROP INDEX idx_te_comm_order_earned_acc_date,
    DROP INDEX idx_te_comm_progress,
    DROP INDEX idx_te_acc_comm_order_earned_date,
    ALGORITHM=INPLACE,
    LOCK=NONE;
