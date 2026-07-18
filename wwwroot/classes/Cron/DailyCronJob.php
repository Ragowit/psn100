<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';

final readonly class DailyCronJob implements CronJobInterface
{
    /**
     * Ranking-window size for scanning trophy_earned for top-10k accounts.
     *
     * Each batch drives from a frozen ranking snapshot into trophy_earned by
     * account_id so HASH(account_id) partition pruning applies. Small windows
     * keep each aggregation bounded so daily cron cannot saturate MySQL/IO and
     * take the public site offline (Cloudflare 525).
     */
    private const int RANKED_OWNER_RANKING_BATCH_SIZE = 100;

    private const int TOP_RANKED_PLAYERS = 10000;

    /**
     * Pause between ranking batches so web traffic can use MySQL while daily
     * rarity recalculation is in progress.
     */
    private const int RANKED_OWNER_BATCH_DELAY_SECONDS = 1;

    private const \Closure DEFAULT_SLEEPER = static function (int $seconds): void {
        sleep($seconds);
    };

    private const string CREATE_RANKED_PLAYER_SNAPSHOT_QUERY = <<<'SQL'
        CREATE TEMPORARY TABLE tmp_daily_ranked_players (
            ranking MEDIUMINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (ranking),
            KEY idx_tmp_daily_ranked_players_account (account_id)
        )
        SQL;

    /**
     * Freeze the top-10k account set once so later player_ranking swaps cannot
     * move an account into another batch window and double-count owners.
     */
    private const string POPULATE_RANKED_PLAYER_SNAPSHOT_QUERY = <<<'SQL'
        INSERT INTO tmp_daily_ranked_players (ranking, account_id)
        SELECT pr.ranking, pr.account_id
        FROM player_ranking pr FORCE INDEX (idx_pr_ranking_account)
        WHERE pr.ranking <= 10000
        SQL;

    private const string CREATE_RANKED_OWNER_TEMP_TABLE_QUERY = <<<'SQL'
        CREATE TEMPORARY TABLE tmp_daily_ranked_trophy_owners (
            np_communication_id VARCHAR(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
            order_id SMALLINT UNSIGNED NOT NULL,
            trophy_owners INT UNSIGNED NOT NULL,
            PRIMARY KEY (np_communication_id, order_id)
        )
        SQL;

    /**
     * Count earned trophies for one ranking window of the frozen top players.
     *
     * Driving from tmp_daily_ranked_players into trophy_earned on account_id
     * applies HASH(account_id) partition pruning and uses
     * idx_te_acc_comm_order_earned_date. Batches accumulate into the temp table
     * via ON DUPLICATE KEY UPDATE so the full top-10k pass never runs as one
     * long, site-blocking statement. This replaces the previous per-title (or
     * title-batch × 10k) probes that re-scanned the same accounts for every
     * game and made daily cron take 10+ hours.
     */
    private const string POPULATE_RANKED_OWNER_COUNTS_QUERY = <<<'SQL'
        INSERT INTO tmp_daily_ranked_trophy_owners (
            np_communication_id,
            order_id,
            trophy_owners
        )
        SELECT /*+ JOIN_ORDER(rp, te) */
            te.np_communication_id,
            te.order_id,
            COUNT(*)
        FROM tmp_daily_ranked_players rp
        STRAIGHT_JOIN trophy_earned te FORCE INDEX (idx_te_acc_comm_order_earned_date)
            ON te.account_id = rp.account_id
            AND te.earned = 1
        WHERE rp.ranking BETWEEN :min_ranking AND :max_ranking
        GROUP BY te.np_communication_id, te.order_id
        ON DUPLICATE KEY UPDATE
            trophy_owners = trophy_owners + VALUES(trophy_owners)
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
        private int $rankedOwnerRankingBatchSize = self::RANKED_OWNER_RANKING_BATCH_SIZE,
        private int $batchDelaySeconds = self::RANKED_OWNER_BATCH_DELAY_SECONDS,
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
            $this->executeWithRetry([$this, 'applyTrophyRarityFromTemporaryTableWithRecovery']);
        } finally {
            // Best-effort: a transient DROP failure must not abort run() before
            // title rarity points. The tables are TEMPORARY/session-scoped anyway.
            try {
                $this->dropRankedOwnerTempTables();
            } catch (Throwable) {
            }
        }
    }

    private function prepareAndPopulateRankedOwnerCounts(): void
    {
        try {
            $this->prepareRankedOwnerTempTables();
            $this->populateRankedOwnerCounts();
        } catch (Throwable $exception) {
            // Do not leave an empty/partial temp table behind. A later apply retry
            // would otherwise see the table exist and write zero/stale rarities.
            try {
                $this->dropRankedOwnerTempTables();
            } catch (Throwable) {
            }

            throw $exception;
        }
    }

    private function prepareRankedOwnerTempTables(): void
    {
        $this->dropRankedOwnerTempTables();
        $this->database->exec(self::CREATE_RANKED_PLAYER_SNAPSHOT_QUERY);
        $this->database->exec(self::CREATE_RANKED_OWNER_TEMP_TABLE_QUERY);

        $snapshot = $this->database->prepare(self::POPULATE_RANKED_PLAYER_SNAPSHOT_QUERY);
        $snapshot->execute();
    }

    private function populateRankedOwnerCounts(): void
    {
        $batchSize = max(1, $this->rankedOwnerRankingBatchSize);
        $query = $this->database->prepare(self::POPULATE_RANKED_OWNER_COUNTS_QUERY);

        for ($minRanking = 1; $minRanking <= self::TOP_RANKED_PLAYERS; $minRanking += $batchSize) {
            if ($minRanking > 1 && $this->batchDelaySeconds > 0) {
                ($this->sleeper)($this->batchDelaySeconds);
            }

            $maxRanking = min($minRanking + $batchSize - 1, self::TOP_RANKED_PLAYERS);
            $query->bindValue(':min_ranking', $minRanking, PDO::PARAM_INT);
            $query->bindValue(':max_ranking', $maxRanking, PDO::PARAM_INT);
            $query->execute();
        }
    }

    /**
     * Apply rarity from the temp table, rebuilding counts if the session-scoped
     * table disappeared (e.g. MySQL session reset after populate succeeded).
     */
    private function applyTrophyRarityFromTemporaryTableWithRecovery(): void
    {
        try {
            $this->applyTrophyRarityFromTemporaryTable();
        } catch (Throwable $exception) {
            if (!$this->isMissingRankedOwnerTempTableError($exception)) {
                throw $exception;
            }

            // Missing-table recovery must populate+apply as one retry unit.
            // Otherwise a failed rebuild can leave an empty temp table (if DROP
            // cleanup also fails) and the next apply-first attempt would write
            // zero owners / LEGENDARY-style rarities for every trophy.
            $this->executeWithRetry([$this, 'preparePopulateAndApplyRankedOwnerRarity']);
        }
    }

    private function preparePopulateAndApplyRankedOwnerRarity(): void
    {
        $this->prepareAndPopulateRankedOwnerCounts();
        $this->applyTrophyRarityFromTemporaryTable();
    }

    private function applyTrophyRarityFromTemporaryTable(): void
    {
        $query = $this->database->prepare(self::APPLY_TROPHY_RARITY_QUERY);
        $query->execute();
    }

    private function isMissingRankedOwnerTempTableError(Throwable $exception): bool
    {
        $message = $exception->getMessage();
        $mentionsTempTable = str_contains($message, 'tmp_daily_ranked_trophy_owners')
            || str_contains($message, 'tmp_daily_ranked_players');

        return $mentionsTempTable
            && (
                str_contains($message, "doesn't exist")
                || str_contains($message, 'does not exist')
                || str_contains($message, 'Base table or view not found')
            );
    }

    private function dropRankedOwnerTempTables(): void
    {
        $this->database->exec('DROP TEMPORARY TABLE IF EXISTS tmp_daily_ranked_trophy_owners');
        $this->database->exec('DROP TEMPORARY TABLE IF EXISTS tmp_daily_ranked_players');
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
