<?php

declare(strict_types=1);

/**
 * Selects the next player for a worker scan from the tiered priority queue.
 *
 * Encapsulates the 9-tier UNION query that was previously embedded in
 * ThirtyMinuteCronJob::run() so the main scan loop can focus on PSN API
 * orchestration.
 */
final class PlayerScanQueueSelector
{
    public function __construct(
        private readonly PDO $database,
    ) {
    }

    /**
     * @return array<string, mixed>|false
     */
    public function selectNextCandidate(int $workerId, ?DateTimeImmutable $referenceTime = null): array|false
    {
        $referenceTime ??= new DateTimeImmutable('now');

        $query = $this->database->prepare($this->selectionSql());
        $query->bindValue(':worker_id', $workerId, PDO::PARAM_INT);

        foreach ($this->buildCutoffs($referenceTime) as $parameter => $value) {
            $query->bindValue($parameter, $value, PDO::PARAM_STR);
        }

        $query->execute();

        $player = $query->fetch(PDO::FETCH_ASSOC);

        return $player === false ? false : $player;
    }

    /**
     * @return array<string, string>
     */
    private function buildCutoffs(DateTimeImmutable $referenceTime): array
    {
        return [
            ':cutoff_1h' => $referenceTime->modify('-1 hour')->format('Y-m-d H:i:s'),
            ':cutoff_1d' => $referenceTime->modify('-1 day')->format('Y-m-d H:i:s'),
            ':cutoff_1w' => $referenceTime->modify('-1 week')->format('Y-m-d H:i:s'),
            ':cutoff_1m' => $referenceTime->modify('-1 month')->format('Y-m-d H:i:s'),
            ':cutoff_3m' => $referenceTime->modify('-3 months')->format('Y-m-d H:i:s'),
        ];
    }

    private function selectionSql(): string
    {
        return <<<'SQL'
            SELECT
                online_id,
                account_id
            FROM (
                SELECT
                    1 AS tier,
                    pq.online_id,
                    pq.request_time AS priority_timestamp,
                    p.account_id
                FROM
                    player_queue pq
                    LEFT JOIN player p ON p.online_id = pq.online_id

                UNION ALL

                SELECT
                    2 AS tier,
                    p.online_id,
                    p.last_updated_date AS priority_timestamp,
                    p.account_id
                FROM
                    player p
                    JOIN player_ranking pr ON pr.account_id = p.account_id
                WHERE
                    (pr.ranking <= 100 OR pr.rarity_ranking <= 100 OR pr.in_game_rarity_ranking <= 100)
                    AND p.last_updated_date < :cutoff_1h

                UNION ALL

                SELECT
                    3 AS tier,
                    p.online_id,
                    p.last_updated_date AS priority_timestamp,
                    p.account_id
                FROM
                    player p
                    JOIN player_ranking pr ON pr.account_id = p.account_id
                WHERE
                    (
                        pr.ranking <= 1000 OR
                        pr.rarity_ranking <= 1000 OR
                        pr.in_game_rarity_ranking <= 1000 OR
                        (pr.ranking BETWEEN 9750 AND 10250) OR
                        (pr.rarity_ranking BETWEEN 9750 AND 10250) OR
                        (pr.in_game_rarity_ranking BETWEEN 9750 AND 10250)
                    )
                    AND p.last_updated_date < :cutoff_1d

                UNION ALL

                SELECT
                    4 AS tier,
                    p.online_id,
                    p.last_updated_date AS priority_timestamp,
                    p.account_id
                FROM
                    player p
                    JOIN player_ranking pr ON pr.account_id = p.account_id
                WHERE
                    (pr.ranking <= 10000 OR pr.rarity_ranking <= 10000 OR pr.in_game_rarity_ranking <= 10000)
                    AND p.last_updated_date < :cutoff_1w

                UNION ALL

                SELECT
                    5 AS tier,
                    p.online_id,
                    p.last_updated_date AS priority_timestamp,
                    p.account_id
                FROM
                    player p
                WHERE
                    p.status = 5
                    AND p.last_updated_date < :cutoff_1d

                UNION ALL

                SELECT
                    6 AS tier,
                    p.online_id,
                    p.last_updated_date AS priority_timestamp,
                    p.account_id
                FROM
                    player p
                    LEFT JOIN player_ranking pr ON pr.account_id = p.account_id
                WHERE
                    p.status NOT IN (1, 3, 4, 5)
                    AND p.last_updated_date < :cutoff_1w
                    AND (
                        pr.account_id IS NULL
                        OR (pr.ranking IS NULL OR pr.ranking > 10000)
                        OR (pr.rarity_ranking IS NULL OR pr.rarity_ranking > 10000)
                        OR (pr.in_game_rarity_ranking IS NULL OR pr.in_game_rarity_ranking > 10000)
                    )

                UNION ALL

                SELECT
                    7 AS tier,
                    p.online_id,
                    p.last_updated_date AS priority_timestamp,
                    p.account_id
                FROM
                    player p
                WHERE
                    p.status = 3
                    AND p.last_updated_date < :cutoff_1m

                UNION ALL

                SELECT
                    8 AS tier,
                    p.online_id,
                    p.last_updated_date AS priority_timestamp,
                    p.account_id
                FROM
                    player p
                WHERE
                    p.status = 4
                    AND p.last_updated_date < :cutoff_3m

                UNION ALL

                SELECT
                    9 AS tier,
                    p.online_id,
                    p.last_updated_date AS priority_timestamp,
                    p.account_id
                FROM
                    player p
                    JOIN player_ranking pr ON pr.account_id = p.account_id
            ) a
            WHERE NOT EXISTS (
                SELECT 1 FROM setting s
                WHERE s.scanning = a.online_id AND s.id != :worker_id
            )
            ORDER BY
                tier,
                priority_timestamp,
                online_id
            LIMIT 1
            SQL;
    }
}
