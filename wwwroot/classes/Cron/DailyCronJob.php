<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';

final readonly class DailyCronJob implements CronJobInterface
{
    public function __construct(private PDO $database, private int $retryDelaySeconds = 3)
    {
    }

    #[\Override]
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
                    t.id AS trophy_id,
                    COUNT(p.account_id) AS trophy_owners,
                    (COUNT(p.account_id) / 10000.0) * 100 AS rarity_percent,
                    CASE
                        WHEN ttm.owners = 0 THEN 0
                        ELSE LEAST((COUNT(p.account_id) / ttm.owners) * 100, 100.0)
                    END AS in_game_rarity_percent
                FROM trophy t
                JOIN trophy_title_meta ttm ON ttm.np_communication_id = t.np_communication_id
                LEFT JOIN trophy_earned te
                    ON te.np_communication_id = t.np_communication_id
                        AND te.order_id = t.order_id
                        AND te.earned = 1
                LEFT JOIN player_ranking p
                    ON p.account_id = te.account_id
                        AND p.ranking <= 10000
                WHERE t.np_communication_id = :np_communication_id
                GROUP BY t.id
                ORDER BY NULL
            )
            UPDATE trophy_meta tm
            JOIN rarity r ON tm.trophy_id = r.trophy_id
            JOIN trophy t ON t.id = tm.trophy_id
            JOIN trophy_title_meta ttm ON ttm.np_communication_id = t.np_communication_id
            SET
                tm.rarity_percent = r.rarity_percent,
                tm.owners = r.trophy_owners,
                tm.rarity_point = IF(
                    tm.status = 0 AND ttm.status = 0,
                    IF(r.rarity_percent = 0, 10000, FLOOR(1 / (r.rarity_percent / 100) - 1)),
                    0
                ),
                tm.rarity_name = CASE
                    WHEN tm.status != 0 OR ttm.status != 0 THEN 'NONE'
                    WHEN r.rarity_percent > 10 THEN 'COMMON'
                    WHEN r.rarity_percent > 2 THEN 'UNCOMMON'
                    WHEN r.rarity_percent > 0.2 THEN 'RARE'
                    WHEN r.rarity_percent > 0.02 THEN 'EPIC'
                    ELSE 'LEGENDARY'
                END,
                tm.in_game_rarity_percent = r.in_game_rarity_percent,
                tm.in_game_rarity_point = IF(
                    tm.status = 0 AND ttm.status = 0 AND ttm.owners > 0,
                    IF(r.in_game_rarity_percent = 0, 10000, FLOOR(1 / (r.in_game_rarity_percent / 100) - 1)),
                    0
                ),
                tm.in_game_rarity_name = CASE
                    WHEN tm.status != 0 OR ttm.status != 0 THEN 'NONE'
                    WHEN r.in_game_rarity_percent <= 1 THEN 'LEGENDARY'
                    WHEN r.in_game_rarity_percent <= 5 THEN 'EPIC'
                    WHEN r.in_game_rarity_percent <= 20 THEN 'RARE'
                    WHEN r.in_game_rarity_percent <= 60 THEN 'UNCOMMON'
                    ELSE 'COMMON'
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
                        t.np_communication_id,
                    IFNULL(SUM(tm.rarity_point), 0) AS rarity_sum,
                    IFNULL(SUM(tm.in_game_rarity_point), 0) AS in_game_rarity_sum
                FROM trophy t
                JOIN trophy_meta tm ON tm.trophy_id = t.id
                GROUP BY t.np_communication_id
            )
            UPDATE trophy_title_meta ttm
            JOIN rarity r USING(np_communication_id)
            SET
                ttm.rarity_points = r.rarity_sum,
                ttm.in_game_rarity_points = r.in_game_rarity_sum"
        );

        $query->execute();
    }

    private function executeWithRetry(callable $operation, mixed ...$arguments): void
    {
        while (true) {
            try {
                $operation(...$arguments);
                return;
            } catch (Throwable $exception) {
                sleep($this->retryDelaySeconds);
            }
        }
    }
}
