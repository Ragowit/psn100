<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';

use Throwable;

final readonly class HourlyCronJob implements CronJobInterface
{
    private const BATCH_SIZE = 500;

    private const STATISTICS_UPDATE_QUERY = <<<'SQL'
        game AS (
            SELECT
                ttp.np_communication_id,
                COUNT(*) AS owners,
                COUNT(CASE WHEN ttp.progress = 100 THEN 1 END) AS owners_completed,
                COUNT(CASE WHEN ttp.last_updated_date >= CURDATE() - INTERVAL 7 DAY THEN 1 END) AS recent_players
            FROM
                trophy_title_player ttp
                JOIN player_ranking pr ON pr.account_id = ttp.account_id AND pr.ranking <= 10000
                JOIN batch b ON b.np_communication_id = ttp.np_communication_id
            GROUP BY
                ttp.np_communication_id
        )
        UPDATE trophy_title_meta ttm
        JOIN batch b ON b.np_communication_id = ttm.np_communication_id
        JOIN game g ON ttm.np_communication_id = g.np_communication_id
        SET
            ttm.owners = g.owners,
            ttm.owners_completed = g.owners_completed,
            ttm.recent_players = g.recent_players,
            ttm.difficulty = IF(g.owners = 0, 0, (g.owners_completed / g.owners) * 100)
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
        $batchQuery = $this->buildBatchUnionQuery(count($batchIds));
        $sql = sprintf(
            "WITH batch AS (%s),\n%s WHERE b.np_communication_id = ttm.np_communication_id",
            $batchQuery,
            self::STATISTICS_UPDATE_QUERY
        );

        $query = $this->database->prepare($sql);
        $query->execute(array_values($batchIds));
    }

    private function buildBatchUnionQuery(int $size): string
    {
        return implode("\nUNION ALL\n", array_fill(0, $size, 'SELECT ? AS np_communication_id'));
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
