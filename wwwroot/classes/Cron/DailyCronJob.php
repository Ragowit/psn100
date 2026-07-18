<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';

final readonly class DailyCronJob implements CronJobInterface
{
    private const \Closure DEFAULT_SLEEPER = static function (int $seconds): void {
        sleep($seconds);
    };

    private const string CREATE_RANKED_OWNER_TEMP_TABLE_QUERY = <<<'SQL'
        CREATE TEMPORARY TABLE tmp_daily_ranked_trophy_owners (
            np_communication_id VARCHAR(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
            order_id SMALLINT UNSIGNED NOT NULL,
            trophy_owners INT UNSIGNED NOT NULL,
            PRIMARY KEY (np_communication_id, order_id)
        )
        SQL;

    /**
     * Count earned trophies for the top 10k ranked players once.
     *
     * Driving from player_ranking into trophy_earned on account_id applies
     * HASH(account_id) partition pruning and uses idx_te_acc_comm_order_earned_date.
     * This replaces the previous per-title (or title-batch × 10k) probes that
     * re-scanned the same accounts for every game and made daily cron take 10+ hours.
     */
    private const string POPULATE_RANKED_OWNER_COUNTS_QUERY = <<<'SQL'
        INSERT INTO tmp_daily_ranked_trophy_owners (np_communication_id, order_id, trophy_owners)
        SELECT
            te.np_communication_id,
            te.order_id,
            COUNT(*)
        FROM player_ranking pr
        INNER JOIN trophy_earned te
            ON te.account_id = pr.account_id
            AND te.earned = 1
        WHERE pr.ranking <= 10000
        GROUP BY te.np_communication_id, te.order_id
        SQL;

    /**
     * Apply rarity for every trophy from the once-built top-10k owner counts.
     *
     * trophy / trophy_meta are small enough for a single UPDATE. Titles never
     * earned by a top-10k player get zero owners via LEFT JOIN + IFNULL, matching
     * the previous zero-owner fast path (10000 / LEGENDARY when obtainable).
     */
    private const string APPLY_TROPHY_RARITY_QUERY = <<<'SQL'
        WITH rarity AS (
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
            FROM trophy t
            JOIN trophy_meta tm ON tm.trophy_id = t.id
            JOIN trophy_title_meta ttm ON ttm.np_communication_id = t.np_communication_id
            LEFT JOIN tmp_daily_ranked_trophy_owners owners
                ON owners.np_communication_id = t.np_communication_id
                AND owners.order_id = t.order_id
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
        $this->recalculateTrophyRarity();
        $this->recalculateTitleRarityPoints();
    }

    private function recalculateTrophyRarity(): void
    {
        try {
            // Populate and apply retry independently so a trophy_meta lock/timeout
            // does not force another full scan of top-10k trophy_earned rows.
            $this->executeWithRetry([$this, 'prepareAndPopulateRankedOwnerCounts']);
            $this->executeWithRetry([$this, 'applyTrophyRarityFromTemporaryTable']);
        } finally {
            $this->dropRankedOwnerTempTable();
        }
    }

    private function prepareAndPopulateRankedOwnerCounts(): void
    {
        $this->prepareRankedOwnerTempTable();
        $this->populateRankedOwnerCounts();
    }

    private function prepareRankedOwnerTempTable(): void
    {
        $this->database->exec('DROP TEMPORARY TABLE IF EXISTS tmp_daily_ranked_trophy_owners');
        $this->database->exec(self::CREATE_RANKED_OWNER_TEMP_TABLE_QUERY);
    }

    private function populateRankedOwnerCounts(): void
    {
        $query = $this->database->prepare(self::POPULATE_RANKED_OWNER_COUNTS_QUERY);
        $query->execute();
    }

    private function applyTrophyRarityFromTemporaryTable(): void
    {
        $query = $this->database->prepare(self::APPLY_TROPHY_RARITY_QUERY);
        $query->execute();
    }

    private function dropRankedOwnerTempTable(): void
    {
        $this->database->exec('DROP TEMPORARY TABLE IF EXISTS tmp_daily_ranked_trophy_owners');
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

    private function executeWithRetry(callable $operation, mixed ...$arguments): mixed
    {
        while (true) {
            try {
                return $operation(...$arguments);
            } catch (Throwable $exception) {
                ($this->sleeper)($this->retryDelaySeconds);
            }
        }
    }
}
