<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';

final readonly class HourlyCronJob implements CronJobInterface
{
    private const BATCH_SIZE = 500;

    private const CREATE_BATCH_TEMP_TABLE_QUERY = <<<'SQL'
        CREATE TEMPORARY TABLE IF NOT EXISTS tmp_hourly_batch (
            np_communication_id VARCHAR(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci PRIMARY KEY
        )
        SQL;

    private const CREATE_STATS_TEMP_TABLE_QUERY = <<<'SQL'
        CREATE TEMPORARY TABLE IF NOT EXISTS tmp_hourly_stats (
            np_communication_id VARCHAR(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci PRIMARY KEY,
            owners INT NOT NULL,
            owners_completed INT NOT NULL,
            recent_players INT NOT NULL
        )
        SQL;

    private const INSERT_STATS_QUERY = <<<'SQL'
        INSERT INTO tmp_hourly_stats (np_communication_id, owners, owners_completed, recent_players)
        SELECT
            ttp.np_communication_id,
            COUNT(*) AS owners,
            SUM(ttp.progress = 100) AS owners_completed,
            SUM(ttp.last_updated_date >= (UTC_TIMESTAMP() - INTERVAL 7 DAY)) AS recent_players
        FROM trophy_title_player ttp
        JOIN tmp_hourly_batch b ON b.np_communication_id = ttp.np_communication_id
        JOIN player_ranking pr ON pr.account_id = ttp.account_id AND pr.ranking <= 10000
        GROUP BY ttp.np_communication_id
        SQL;

    private const UPDATE_META_QUERY = <<<'SQL'
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

    public function __construct(private PDO $database, private int $retryDelaySeconds = 3)
    {
    }

    #[\Override]
    public function run(): void
    {
        $this->executeWithRetry([$this, 'updateTrophyTitleStatistics']);
    }

    private function updateTrophyTitleStatistics(): void
    {
        $lastId = null;

        $this->initializeTemporaryTables();

        try {
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

                $lastId = end($batchIds);
            }
        } finally {
            $this->dropTemporaryTables();
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

        $this->database->exec('DELETE FROM tmp_hourly_stats');
        $insertStatsQuery = $this->database->prepare(self::INSERT_STATS_QUERY);
        $insertStatsQuery->execute();

        $updateMetaQuery = $this->database->prepare(self::UPDATE_META_QUERY);
        $updateMetaQuery->execute();
    }

    private function initializeTemporaryTables(): void
    {
        $this->database->exec(self::CREATE_BATCH_TEMP_TABLE_QUERY);
        $this->database->exec(self::CREATE_STATS_TEMP_TABLE_QUERY);
    }

    private function dropTemporaryTables(): void
    {
        $this->database->exec('DROP TEMPORARY TABLE IF EXISTS tmp_hourly_stats');
        $this->database->exec('DROP TEMPORARY TABLE IF EXISTS tmp_hourly_batch');
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

    private function executeWithRetry(callable $operation): void
    {
        while (true) {
            try {
                $operation();

                return;
            } catch (Throwable $exception) {
                sleep($this->retryDelaySeconds);
            }
        }
    }
}
