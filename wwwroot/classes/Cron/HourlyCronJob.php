<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';

final readonly class HourlyCronJob implements CronJobInterface
{
    private const UPDATE_ALL_META_QUERY = <<<'SQL'
        UPDATE trophy_title_meta ttm
        LEFT JOIN (
            SELECT
                ttp.np_communication_id,
                COUNT(*) AS owners,
                SUM(ttp.progress = 100) AS owners_completed,
                SUM(ttp.last_updated_date >= (UTC_TIMESTAMP() - INTERVAL 7 DAY)) AS recent_players
            FROM trophy_title_player ttp
            JOIN player_ranking pr ON pr.account_id = ttp.account_id
            WHERE pr.ranking <= 10000
            GROUP BY ttp.np_communication_id
        ) s ON s.np_communication_id = ttm.np_communication_id
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
        $this->executeWithRetry(function (): void {
            $query = $this->database->prepare(self::UPDATE_ALL_META_QUERY);
            $query->execute();
        });
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
