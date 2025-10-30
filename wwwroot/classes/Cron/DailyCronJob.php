<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';

class DailyCronJob implements CronJobInterface
{
    private PDO $database;

    private int $retryDelaySeconds;

    public function __construct(PDO $database, int $retryDelaySeconds = 3)
    {
        $this->database = $database;
        $this->retryDelaySeconds = $retryDelaySeconds;
    }

    public function run(): void
    {
        $this->recalculateTrophyRarityForGames();
        $this->recalculateTitleRarityPoints();
    }

    private function recalculateTrophyRarityForGames(): void
    {
        $query = $this->database->prepare(
            'SELECT np_communication_id FROM trophy_title ORDER BY id DESC'
        );
        $query->execute();
        $games = $query->fetchAll(PDO::FETCH_COLUMN);

        foreach ($games as $npCommunicationId) {
            if (!is_string($npCommunicationId)) {
                continue;
            }

            $this->executeWithRetry([$this, 'updateTrophyRarityForGame'], $npCommunicationId);
        }
    }

    private function updateTrophyRarityForGame(string $npCommunicationId): void
    {
        $query = $this->database->prepare(
            "WITH rarity AS (
                SELECT
                    t.order_id,
                    COUNT(p.account_id) AS trophy_owners,
                    (COUNT(p.account_id) / 10000.0) * 100 AS rarity_percent
                FROM trophy t
                LEFT JOIN trophy_earned te
                    ON te.np_communication_id = t.np_communication_id
                        AND te.order_id = t.order_id
                        AND te.earned = 1
                LEFT JOIN player_ranking p
                    ON p.account_id = te.account_id
                        AND p.ranking <= 10000
                WHERE t.np_communication_id = :np_communication_id
                GROUP BY order_id
                ORDER BY NULL
            )
            UPDATE trophy t
            JOIN rarity r USING(order_id)
            JOIN trophy_title tt USING(np_communication_id)
            JOIN trophy_title_meta ttm USING (np_communication_id)
            SET
                t.rarity_percent = r.rarity_percent,
                t.rarity_point = IF(
                    t.status = 0 AND ttm.status = 0,
                    IF(r.rarity_percent = 0, 99999, FLOOR(1 / (r.rarity_percent / 100) - 1)),
                    0
                ),
                t.rarity_name = CASE
                    WHEN t.status != 0 OR ttm.status != 0 THEN 'NONE'
                    WHEN r.rarity_percent > 10 THEN 'COMMON'
                    WHEN r.rarity_percent > 2 THEN 'UNCOMMON'
                    WHEN r.rarity_percent > 0.2 THEN 'RARE'
                    WHEN r.rarity_percent > 0.02 THEN 'EPIC'
                    ELSE 'LEGENDARY'
                END
            WHERE t.np_communication_id = :np_communication_id"
        );

        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }

    private function recalculateTitleRarityPoints(): void
    {
        $this->executeWithRetry([$this, 'updateTrophyTitleRarityPoints']);
    }

    private function updateTrophyTitleRarityPoints(): void
    {
        $query = $this->database->prepare(
            "WITH rarity AS (
                SELECT
                    np_communication_id,
                    IFNULL(SUM(rarity_point), 0) AS rarity_sum
                FROM trophy
                WHERE `status` = 0
                GROUP BY np_communication_id
            )
            UPDATE trophy_title_meta ttm
            JOIN rarity r USING(np_communication_id)
            SET ttm.rarity_points = r.rarity_sum"
        );

        $query->execute();
    }

    private function executeWithRetry(callable $operation, ...$arguments): void
    {
        while (true) {
            try {
                $operation(...$arguments);
                return;
            } catch (Exception $exception) {
                sleep($this->retryDelaySeconds);
            }
        }
    }
}
