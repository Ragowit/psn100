<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';

final readonly class HourlyCronJob implements CronJobInterface
{
    private const int BATCH_SIZE = 500;

    private const int TOP_RANKED_PLAYERS = 10000;

    private const string CREATE_BATCH_TEMP_TABLE_QUERY = <<<'SQL'
        CREATE TEMPORARY TABLE IF NOT EXISTS tmp_hourly_batch (
            np_communication_id VARCHAR(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci PRIMARY KEY
        )
        SQL;

    private const string CREATE_STATS_TEMP_TABLE_QUERY = <<<'SQL'
        CREATE TEMPORARY TABLE IF NOT EXISTS tmp_hourly_stats (
            np_communication_id VARCHAR(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci PRIMARY KEY,
            owners INT NOT NULL,
            owners_completed INT NOT NULL,
            recent_players INT NOT NULL
        )
        SQL;

    /**
     * Freeze the top-10k account set once so later player_ranking swaps (every
     * 5th minute) cannot change which players qualify while title batches run.
     *
     * account_id is unique in player_ranking; RANK() ties may share the same
     * ranking value, so membership is defined by ranking <= 10000 at snapshot
     * time rather than by a dense top-N cut.
     */
    private const string CREATE_RANKED_PLAYER_SNAPSHOT_QUERY = <<<'SQL'
        CREATE TEMPORARY TABLE tmp_hourly_ranked_players (
            account_id BIGINT UNSIGNED NOT NULL,
            ranking MEDIUMINT UNSIGNED NOT NULL,
            PRIMARY KEY (account_id),
            KEY idx_tmp_hourly_ranked_players_ranking (ranking, account_id)
        )
        SQL;

    private const string POPULATE_RANKED_PLAYER_SNAPSHOT_QUERY = <<<'SQL'
        INSERT INTO tmp_hourly_ranked_players (account_id, ranking)
        SELECT
            pr.account_id,
            pr.ranking
        FROM player_ranking pr FORCE INDEX (idx_pr_ranking_account)
        WHERE pr.ranking <= 10000
        SQL;

    /**
     * Aggregate all top-10k title stats once. Re-running this per 500-title
     * batch previously stretched hourly runtime from ~1 minute to 10+ minutes
     * and made PROCESSLIST look like INSERT INTO tmp_hourly_stats was
     * "restarting" on every batch.
     */
    private const string POPULATE_ALL_STATS_QUERY = <<<'SQL'
        INSERT INTO tmp_hourly_stats (np_communication_id, owners, owners_completed, recent_players)
        SELECT
            ttp.np_communication_id,
            COUNT(*) AS owners,
            SUM(ttp.progress = 100) AS owners_completed,
            SUM(ttp.last_updated_date >= (UTC_TIMESTAMP() - INTERVAL 7 DAY)) AS recent_players
        FROM trophy_title_player ttp
        JOIN tmp_hourly_ranked_players rp ON rp.account_id = ttp.account_id
        GROUP BY ttp.np_communication_id
        SQL;

    private const string UPDATE_META_QUERY = <<<'SQL'
        UPDATE trophy_title_meta ttm
        JOIN tmp_hourly_batch b ON b.np_communication_id = ttm.np_communication_id
        LEFT JOIN tmp_hourly_stats s ON s.np_communication_id = ttm.np_communication_id
        SET
            ttm.owners = COALESCE(s.owners, 0),
            ttm.owners_completed = COALESCE(s.owners_completed, 0),
            ttm.recent_players = COALESCE(s.recent_players, 0),
            ttm.difficulty = IF(
                COALESCE(s.owners, 0) = 0,
                0,
                (COALESCE(s.owners_completed, 0) / COALESCE(s.owners, 0)) * 100
            )
        SQL;

    public function __construct(
        final private PDO $database,
        final private int $retryDelaySeconds = 3,
        final private \Closure $sleeper = sleep(...),
    ) {
    }

    #[\Override]
    public function run(): void
    {
        try {
            // Populate and apply retry independently so a trophy_title_meta
            // lock/timeout does not force another full trophy_title_player scan.
            $this->executeWithRetry($this->prepareAndPopulateStatistics(...));
            $this->executeWithRetry($this->applyStatisticsInBatchesWithRecovery(...));
        } finally {
            // Best-effort: a transient DROP failure must not abort run().
            // The tables are TEMPORARY/session-scoped anyway.
            try {
                $this->dropTemporaryTables();
            } catch (Throwable) {
            }
        }
    }

    private function prepareAndPopulateStatistics(): void
    {
        try {
            $this->initializeTemporaryTables();
            $this->populateAllStatistics();
        } catch (Throwable $exception) {
            // Do not leave a partial stats table behind. A later apply retry
            // would otherwise see the table exist and write zero/stale values.
            try {
                $this->dropTemporaryTables();
            } catch (Throwable) {
            }

            throw $exception;
        }
    }

    private function populateAllStatistics(): void
    {
        $query = $this->database->prepare(self::POPULATE_ALL_STATS_QUERY);
        $query->execute();
    }

    /**
     * Apply precomputed stats in short batched UPDATE transactions. If the
     * session-scoped temp tables disappeared (e.g. MySQL session reset after
     * populate succeeded), rebuild populate+apply as one retry unit.
     */
    private function applyStatisticsInBatchesWithRecovery(): void
    {
        try {
            $this->applyStatisticsInBatches();
        } catch (Throwable $exception) {
            if (!$this->isMissingTempTableError($exception)) {
                throw $exception;
            }

            $this->executeWithRetry($this->preparePopulateAndApplyStatistics(...));
        }
    }

    private function preparePopulateAndApplyStatistics(): void
    {
        $this->prepareAndPopulateStatistics();
        $this->applyStatisticsInBatches();
    }

    private function applyStatisticsInBatches(): void
    {
        $lastId = null;

        while (true) {
            $batchIds = $this->getBatchNpCommunicationIds($lastId, self::BATCH_SIZE);

            if ($batchIds === []) {
                break;
            }

            $this->database->beginTransaction();

            try {
                $this->updateStatisticsForBatch($batchIds);
                $this->database->commit();
            } catch (Exception $exception) {
                $this->database->rollBack();

                throw $exception;
            }

            $lastId = array_last($batchIds);
        }
    }

    private function getBatchNpCommunicationIds(?string $lastId, int $limit): array
    {
        $baseQuery = 'SELECT np_communication_id FROM trophy_title_meta %s ORDER BY np_communication_id LIMIT :limit';

        if ($lastId === null) {
            $query = $this->database->prepare(sprintf($baseQuery, ''));
        } else {
            $query = $this->database->prepare(sprintf($baseQuery, 'WHERE np_communication_id > :last_id'));
            $query->bindValue(':last_id', $lastId, PDO::PARAM_STR);
        }

        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    private function updateStatisticsForBatch(array $batchIds): void
    {
        $this->database->exec('DELETE FROM tmp_hourly_batch');
        $this->insertBatchIdsIntoTemporaryTable($batchIds);

        $updateMetaQuery = $this->database->prepare(self::UPDATE_META_QUERY);
        $updateMetaQuery->execute();
    }

    private function initializeTemporaryTables(): void
    {
        $this->dropTemporaryTables();
        $this->database->exec(self::CREATE_BATCH_TEMP_TABLE_QUERY);
        $this->database->exec(self::CREATE_STATS_TEMP_TABLE_QUERY);
        $this->prepareRankedPlayerSnapshot();
    }

    /**
     * Capture the current top-10k ranking set before any title batches run.
     */
    private function prepareRankedPlayerSnapshot(): void
    {
        $this->database->exec('DROP TEMPORARY TABLE IF EXISTS tmp_hourly_ranked_players');
        $this->database->exec(self::CREATE_RANKED_PLAYER_SNAPSHOT_QUERY);

        $snapshot = $this->database->prepare(self::POPULATE_RANKED_PLAYER_SNAPSHOT_QUERY);
        $snapshot->execute();
    }

    private function dropTemporaryTables(): void
    {
        $this->database->exec('DROP TEMPORARY TABLE IF EXISTS tmp_hourly_stats');
        $this->database->exec('DROP TEMPORARY TABLE IF EXISTS tmp_hourly_batch');
        $this->database->exec('DROP TEMPORARY TABLE IF EXISTS tmp_hourly_ranked_players');
    }

    private function insertBatchIdsIntoTemporaryTable(array $batchIds): void
    {
        $placeholders = implode(', ', array_fill(0, count($batchIds), '(?)'));
        $query = $this->database->prepare(
            sprintf(
                'INSERT INTO tmp_hourly_batch (np_communication_id) VALUES %s',
                $placeholders
            )
        );
        $query->execute(array_values($batchIds));
    }

    private function isMissingTempTableError(Throwable $exception): bool
    {
        $message = $exception->getMessage();
        $mentionsTempTable = str_contains($message, 'tmp_hourly_stats')
            || str_contains($message, 'tmp_hourly_batch')
            || str_contains($message, 'tmp_hourly_ranked_players');

        return $mentionsTempTable
            && (
                str_contains($message, "doesn't exist")
                || str_contains($message, 'does not exist')
                || str_contains($message, 'Base table or view not found')
            );
    }

    private function executeWithRetry(\Closure $operation): void
    {
        while (true) {
            try {
                $operation();

                return;
            } catch (Throwable $exception) {
                ($this->sleeper)($this->retryDelaySeconds);
            }
        }
    }
}
