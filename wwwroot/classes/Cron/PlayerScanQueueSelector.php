<?php

declare(strict_types=1);

require_once __DIR__ . '/../PlayerStatus.php';

/**
 * Selects the next player for a worker scan from the tiered priority queue.
 *
 * Encapsulates the tiered UNION query that was previously embedded in
 * ThirtyMinuteCronJob::run() so the main scan loop can focus on PSN API
 * orchestration.
 *
 * Ranking tiers are split into per-index range seeks (UNION ALL) so MySQL 8.4
 * can use idx_pr_ranking_account / idx_pr_rarity_ranking_account /
 * idx_pr_in_game_rarity_ranking_account instead of filtering an OR across all
 * three ranking columns.
 *
 * Stale-player cutoffs are computed with MySQL NOW() so they stay aligned with
 * the database session clock and INTERVAL month arithmetic.
 */
final class PlayerScanQueueSelector
{
    public function __construct(
        private readonly PDO $database,
        private readonly ?string $selectionSql = null,
    ) {
    }

    /**
     * @return array<string, mixed>|false
     */
    public function selectNextCandidate(int $workerId): array|false
    {
        $query = $this->database->prepare($this->selectionSql ?? self::mysqlSelectionSql());
        $query->bindValue(':worker_id', $workerId, PDO::PARAM_INT);
        $query->execute();

        $player = $query->fetch(PDO::FETCH_ASSOC);

        return $player === false ? false : $player;
    }

    private static function mysqlSelectionSql(): string
    {
        return self::buildSelectionSql(<<<'SQL'
            SELECT
                NOW() AS now,
                NOW() - INTERVAL 1 HOUR AS cutoff_1h,
                NOW() - INTERVAL 1 DAY AS cutoff_1d,
                NOW() - INTERVAL 1 WEEK AS cutoff_1w,
                NOW() - INTERVAL 1 MONTH AS cutoff_1m,
                NOW() - INTERVAL 3 MONTH AS cutoff_3m
            SQL);
    }

    /**
     * @internal Visible for tests that need deterministic cutoff values on SQLite.
     */
    public static function selectionSqlWithLiteralCutoffs(
        string $now,
        string $cutoff1Hour,
        string $cutoff1Day,
        string $cutoff1Week,
        string $cutoff1Month,
        string $cutoff3Months,
    ): string {
        return self::buildSelectionSql(sprintf(
            "SELECT
                '%s' AS now,
                '%s' AS cutoff_1h,
                '%s' AS cutoff_1d,
                '%s' AS cutoff_1w,
                '%s' AS cutoff_1m,
                '%s' AS cutoff_3m",
            $now,
            $cutoff1Hour,
            $cutoff1Day,
            $cutoff1Week,
            $cutoff1Month,
            $cutoff3Months,
        ));
    }

    private static function buildSelectionSql(string $nowValuesSelect): string
    {
        $tier2 = self::rankingTierBranches(
            tier: 2,
            cutoffColumn: 'cutoff_1h',
            predicates: [
                'pr.ranking <= 100',
                'pr.rarity_ranking <= 100',
                'pr.in_game_rarity_ranking <= 100',
            ],
        );

        $tier3 = self::rankingTierBranches(
            tier: 3,
            cutoffColumn: 'cutoff_1d',
            predicates: [
                'pr.ranking <= 1000',
                'pr.rarity_ranking <= 1000',
                'pr.in_game_rarity_ranking <= 1000',
                'pr.ranking BETWEEN 9750 AND 10250',
                'pr.rarity_ranking BETWEEN 9750 AND 10250',
                'pr.in_game_rarity_ranking BETWEEN 9750 AND 10250',
            ],
        );

        $tier4 = self::rankingTierBranches(
            tier: 4,
            cutoffColumn: 'cutoff_1w',
            predicates: [
                'pr.ranking <= 10000',
                'pr.rarity_ranking <= 10000',
                'pr.in_game_rarity_ranking <= 10000',
            ],
        );

        $unavailableStatus = PlayerStatus::UNAVAILABLE->value;
        $normalStatus = PlayerStatus::NORMAL->value;
        $newPlayerStatus = PlayerStatus::NEW_PLAYER->value;
        $privateStatus = PlayerStatus::PRIVATE_PROFILE->value;
        $inactiveStatus = PlayerStatus::INACTIVE->value;

        return <<<SQL
            WITH
                now_values AS (
                    {$nowValuesSelect}
                )
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

                {$tier2}

                UNION ALL

                {$tier3}

                UNION ALL

                {$tier4}

                UNION ALL

                SELECT
                    5 AS tier,
                    p.online_id,
                    p.last_updated_date AS priority_timestamp,
                    p.account_id
                FROM
                    player p
                    JOIN now_values nv
                WHERE
                    p.status = {$unavailableStatus}
                    AND p.last_updated_date < nv.cutoff_1d

                UNION ALL

                SELECT
                    6 AS tier,
                    p.online_id,
                    p.last_updated_date AS priority_timestamp,
                    p.account_id
                FROM
                    player p
                    LEFT JOIN player_ranking pr ON pr.account_id = p.account_id
                    JOIN now_values nv
                WHERE
                    p.status IN ({$normalStatus}, {$newPlayerStatus})
                    AND p.last_updated_date < nv.cutoff_1w
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
                    JOIN now_values nv
                WHERE
                    p.status = {$privateStatus}
                    AND p.last_updated_date < nv.cutoff_1m

                UNION ALL

                SELECT
                    8 AS tier,
                    p.online_id,
                    p.last_updated_date AS priority_timestamp,
                    p.account_id
                FROM
                    player p
                    JOIN now_values nv
                WHERE
                    p.status = {$inactiveStatus}
                    AND p.last_updated_date < nv.cutoff_3m

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

    /**
     * @param list<string> $predicates
     */
    private static function rankingTierBranches(int $tier, string $cutoffColumn, array $predicates): string
    {
        $branches = [];

        foreach ($predicates as $predicate) {
            $branches[] = <<<SQL
                SELECT
                    {$tier} AS tier,
                    p.online_id,
                    p.last_updated_date AS priority_timestamp,
                    p.account_id
                FROM
                    player_ranking pr
                    JOIN player p ON p.account_id = pr.account_id
                    JOIN now_values nv
                WHERE
                    {$predicate}
                    AND p.last_updated_date < nv.{$cutoffColumn}
                SQL;
        }

        return implode("\n\n                UNION ALL\n\n", $branches);
    }
}
