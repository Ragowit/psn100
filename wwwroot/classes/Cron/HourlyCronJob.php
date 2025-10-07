<?php

declare(strict_types=1);

require_once __DIR__ . '/RetryableOperationExecutor.php';

class HourlyCronJob
{
    private const STATISTICS_UPDATE_QUERY = <<<'SQL'
        WITH game AS (
            SELECT
                ttp.np_communication_id,
                COUNT(*) AS owners,
                COUNT(CASE WHEN ttp.progress = 100 THEN 1 END) AS owners_completed,
                COUNT(CASE WHEN ttp.last_updated_date >= CURDATE() - INTERVAL 7 DAY THEN 1 END) AS recent_players
            FROM
                trophy_title_player ttp
                JOIN player_ranking pr ON pr.account_id = ttp.account_id AND pr.ranking <= 10000
            GROUP BY
                ttp.np_communication_id
        )
        UPDATE trophy_title tt
        JOIN game g ON tt.np_communication_id = g.np_communication_id
        SET
            tt.owners = g.owners,
            tt.owners_completed = g.owners_completed,
            tt.recent_players = g.recent_players,
            tt.difficulty = IF(g.owners = 0, 0, (g.owners_completed / g.owners) * 100)
        SQL;

    private PDO $database;

    private RetryableOperationExecutor $operationExecutor;

    public function __construct(
        PDO $database,
        int $retryDelaySeconds = 3,
        ?RetryableOperationExecutor $operationExecutor = null
    ) {
        $this->database = $database;
        $this->operationExecutor = $operationExecutor ?? new RetryableOperationExecutor(
            $retryDelaySeconds,
            [Exception::class]
        );
    }

    public function run(): void
    {
        $this->operationExecutor->execute([$this, 'updateTrophyTitleStatistics']);
    }

    private function updateTrophyTitleStatistics(): void
    {
        $query = $this->database->prepare(self::STATISTICS_UPDATE_QUERY);
        $query->execute();
    }

}
