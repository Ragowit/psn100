<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';

final readonly class DailyCronJob implements CronJobInterface
{
    private const \Closure DEFAULT_SLEEPER = static function (int $seconds): void {
        sleep($seconds);
    };

    /**
     * Recalculate rarity for one title.
     *
     * Owners are counted by driving from player_ranking (top 10k) into
     * trophy_earned on account_id so HASH(account_id) partition pruning applies.
     * The aggregated derived table is materialized once per title instead of
     * scanning trophy_earned by np_communication_id across all 256 partitions.
     */
    private const string UPDATE_TROPHY_RARITY_QUERY = <<<'SQL'
        WITH title AS (
            SELECT CAST(:np_communication_id AS CHAR(12)) AS np_communication_id
        ),
        ranked_owners AS (
            SELECT
                te.order_id,
                COUNT(*) AS trophy_owners
            FROM title
            JOIN player_ranking pr ON pr.ranking <= 10000
            INNER JOIN trophy_earned te
                ON te.account_id = pr.account_id
                AND te.np_communication_id = title.np_communication_id
                AND te.earned = 1
            GROUP BY te.order_id
        ),
        rarity AS (
            SELECT
                t.id AS trophy_id,
                tm.status AS meta_status,
                ttm.status AS title_status,
                ttm.owners AS title_owners,
                IFNULL(owners.trophy_owners, 0) AS trophy_owners,
                (IFNULL(owners.trophy_owners, 0) / 10000.0) * 100 AS rarity_percent,
                CASE
                    WHEN ttm.owners = 0 THEN 0
                    ELSE LEAST((IFNULL(owners.trophy_owners, 0) / ttm.owners) * 100, 100.0)
                END AS in_game_rarity_percent
            FROM title
            JOIN trophy t ON t.np_communication_id = title.np_communication_id
            JOIN trophy_meta tm ON tm.trophy_id = t.id
            JOIN trophy_title_meta ttm ON ttm.np_communication_id = t.np_communication_id
            LEFT JOIN ranked_owners owners ON owners.order_id = t.order_id
        )
        UPDATE trophy_meta tm
        JOIN rarity r ON tm.trophy_id = r.trophy_id
        SET
            tm.rarity_percent = r.rarity_percent,
            tm.owners = r.trophy_owners,
            tm.rarity_point = IF(
                r.meta_status = 0 AND r.title_status = 0,
                IF(r.rarity_percent = 0, 10000, FLOOR(1 / (r.rarity_percent / 100) - 1)),
                0
            ),
            tm.rarity_name = CASE
                WHEN r.meta_status != 0 OR r.title_status != 0 THEN 'NONE'
                WHEN r.rarity_percent > 10 THEN 'COMMON'
                WHEN r.rarity_percent > 2 THEN 'UNCOMMON'
                WHEN r.rarity_percent > 0.2 THEN 'RARE'
                WHEN r.rarity_percent > 0.02 THEN 'EPIC'
                ELSE 'LEGENDARY'
            END,
            tm.in_game_rarity_percent = r.in_game_rarity_percent,
            tm.in_game_rarity_point = IF(
                r.meta_status = 0 AND r.title_status = 0 AND r.title_owners > 0,
                IF(r.in_game_rarity_percent = 0, 0, FLOOR(1 / (r.in_game_rarity_percent / 100) - 1)),
                0
            ),
            tm.in_game_rarity_name = CASE
                WHEN r.meta_status != 0 OR r.title_status != 0 THEN 'NONE'
                WHEN r.in_game_rarity_percent <= 1 THEN 'LEGENDARY'
                WHEN r.in_game_rarity_percent <= 5 THEN 'EPIC'
                WHEN r.in_game_rarity_percent <= 20 THEN 'RARE'
                WHEN r.in_game_rarity_percent <= 60 THEN 'UNCOMMON'
                ELSE 'COMMON'
            END
        SQL;

    /**
     * Titles with zero owners have zero trophy_owners in the top-10k join, so
     * rarity_percent is always 0. Skip probing trophy_earned, but keep the same
     * zero-percent scoring as UPDATE_TROPHY_RARITY_QUERY (10000 / LEGENDARY for
     * obtainable trophies; in-game points stay 0 when title owners are 0).
     */
    private const string UPDATE_TROPHY_RARITY_ZERO_OWNERS_QUERY = <<<'SQL'
        UPDATE trophy_meta tm
        JOIN trophy t ON t.id = tm.trophy_id
        JOIN trophy_title_meta ttm ON ttm.np_communication_id = t.np_communication_id
        SET
            tm.owners = 0,
            tm.rarity_percent = 0,
            tm.rarity_point = IF(tm.status = 0 AND ttm.status = 0, 10000, 0),
            tm.rarity_name = IF(tm.status = 0 AND ttm.status = 0, 'LEGENDARY', 'NONE'),
            tm.in_game_rarity_percent = 0,
            tm.in_game_rarity_point = 0,
            tm.in_game_rarity_name = IF(tm.status = 0 AND ttm.status = 0, 'LEGENDARY', 'NONE')
        WHERE t.np_communication_id = :np_communication_id
        SQL;

    private const string UPDATE_TITLE_RARITY_POINTS_QUERY = <<<'SQL'
        WITH rarity AS (
            SELECT
                t.np_communication_id,
                IFNULL(SUM(tm.rarity_point), 0) AS rarity_sum,
                IFNULL(SUM(tm.in_game_rarity_point), 0) AS in_game_rarity_sum
            FROM trophy t
            JOIN trophy_meta tm ON tm.trophy_id = t.id
            GROUP BY t.np_communication_id
        )
        UPDATE trophy_title_meta ttm
        JOIN rarity r USING (np_communication_id)
        SET
            ttm.rarity_points = r.rarity_sum,
            ttm.in_game_rarity_points = r.in_game_rarity_sum
        SQL;

    public function __construct(
        private PDO $database,
        private int $retryDelaySeconds = 3,
        private \Closure $sleeper = self::DEFAULT_SLEEPER,
    ) {
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
        $rankedOwnerTitles = $this->fetchTopTenThousandOwnerTitleLookup();

        foreach ($games as $npCommunicationId) {
            if (!is_string($npCommunicationId)) {
                continue;
            }

            $this->executeWithRetry(
                [$this, 'updateTrophyRarityForGame'],
                $npCommunicationId,
                isset($rankedOwnerTitles[$npCommunicationId]),
            );
        }
    }

    /**
     * @return array<string, true>
     */
    private function fetchTopTenThousandOwnerTitleLookup(): array
    {
        // Do not trust trophy_title_meta.owners alone: hourly cache can lag behind
        // freshly scanned trophy_title_player / trophy_earned rows. Match the rarity
        // query's top-10k definition before taking the zero-owners fast path.
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT DISTINCT ttp.np_communication_id
            FROM player_ranking pr
            JOIN trophy_title_player ttp ON ttp.account_id = pr.account_id
            WHERE pr.ranking <= 10000
            SQL
        );
        $query->execute();

        $rankedOwnerTitles = [];
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $npCommunicationId) {
            if (is_string($npCommunicationId)) {
                $rankedOwnerTitles[$npCommunicationId] = true;
            }
        }

        return $rankedOwnerTitles;
    }

    private function updateTrophyRarityForGame(string $npCommunicationId, bool $hasRankedOwners): void
    {
        $sql = !$hasRankedOwners
            ? self::UPDATE_TROPHY_RARITY_ZERO_OWNERS_QUERY
            : self::UPDATE_TROPHY_RARITY_QUERY;

        $query = $this->database->prepare($sql);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }

    private function recalculateTitleRarityPoints(): void
    {
        $this->executeWithRetry([$this, 'updateTrophyTitleRarityPoints']);
    }

    private function updateTrophyTitleRarityPoints(): void
    {
        $query = $this->database->prepare(self::UPDATE_TITLE_RARITY_POINTS_QUERY);
        $query->execute();
    }

    private function executeWithRetry(callable $operation, mixed ...$arguments): void
    {
        while (true) {
            try {
                $operation(...$arguments);
                return;
            } catch (Throwable $exception) {
                ($this->sleeper)($this->retryDelaySeconds);
            }
        }
    }
}
