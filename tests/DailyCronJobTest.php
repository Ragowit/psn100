<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/DailyCronJob.php';

final class DailyCronJobTest extends TestCase
{
    public function testPopulateQueryDrivesTrophyEarnedFromFrozenRankingSnapshot(): void
    {
        $source = $this->readPrivateConstant('POPULATE_RANKED_OWNER_COUNTS_QUERY');

        $this->assertStringContainsString('SELECT /*+ JOIN_ORDER(rp, te) */', $source);
        $this->assertStringContainsString('FROM tmp_daily_ranked_players rp', $source);
        $this->assertStringContainsString('STRAIGHT_JOIN trophy_earned te FORCE INDEX (idx_te_acc_comm_order_earned_date)', $source);
        $this->assertStringContainsString('te.account_id = rp.account_id', $source);
        $this->assertStringContainsString('te.earned = 1', $source);
        $this->assertStringContainsString('WHERE rp.batch_position BETWEEN :min_position AND :max_position', $source);
        $this->assertStringContainsString('GROUP BY te.np_communication_id, te.order_id', $source);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $source);
        $this->assertStringContainsString(
            'trophy_owners = trophy_owners + VALUES(trophy_owners)',
            $source,
        );
        $this->assertFalse(str_contains($source, 'player_ranking'));
        $this->assertFalse(str_contains($source, 'rp.ranking BETWEEN'));
        $this->assertFalse(str_contains($source, 'LEFT JOIN trophy_earned te'));
        $this->assertFalse(str_contains($source, 'JSON_TABLE('));
        $this->assertFalse(str_contains($source, 'np_communication_id = title.np_communication_id'));
    }

    public function testRankedPlayerSnapshotFreezesTopTenThousandFromPlayerRanking(): void
    {
        $create = $this->readPrivateConstant('CREATE_RANKED_PLAYER_SNAPSHOT_QUERY');
        $populate = $this->readPrivateConstant('POPULATE_RANKED_PLAYER_SNAPSHOT_QUERY');

        $this->assertStringContainsString('CREATE TEMPORARY TABLE tmp_daily_ranked_players', $create);
        $this->assertStringContainsString('PRIMARY KEY (batch_position)', $create);
        $this->assertStringContainsString('UNIQUE KEY u_tmp_daily_ranked_players_account (account_id)', $create);
        // RANK() ties are allowed in player_ranking; batch by dense row numbers instead.
        $this->assertStringContainsString(
            'INSERT INTO tmp_daily_ranked_players (batch_position, ranking, account_id)',
            $populate,
        );
        $this->assertStringContainsString(
            'ROW_NUMBER() OVER (ORDER BY pr.ranking, pr.account_id) AS batch_position',
            $populate,
        );
        $this->assertStringContainsString('FROM player_ranking pr FORCE INDEX (idx_pr_ranking_account)', $populate);
        $this->assertStringContainsString('WHERE pr.ranking <= 10000', $populate);
    }

    public function testApplyTrophyRarityQueryUsesTempTableOwnerCountsAndTopTenThousandDenominator(): void
    {
        $source = $this->readPrivateConstant('APPLY_TROPHY_RARITY_QUERY');

        $this->assertStringContainsString('LEFT JOIN tmp_daily_ranked_trophy_owners owners', $source);
        $this->assertStringContainsString('/ 10000.0) * 100 AS rarity_percent', $source);
        $this->assertStringContainsString('IFNULL(owners.trophy_owners, 0) AS trophy_owners', $source);
        $this->assertStringContainsString('IF(r.rarity_percent = 0, 10000, FLOOR(1 / (r.rarity_percent / 100) - 1))', $source);
        $this->assertFalse(str_contains($source, 'trophy_earned'));
        $this->assertFalse(str_contains($source, 'player_ranking'));
    }

    public function testApplyTrophyRarityQueryAssignsRarityNamesFromThresholds(): void
    {
        $source = $this->readPrivateConstant('APPLY_TROPHY_RARITY_QUERY');

        $this->assertStringContainsString("WHEN r.rarity_percent > 10 THEN 'COMMON'", $source);
        $this->assertStringContainsString("WHEN r.rarity_percent > 2 THEN 'UNCOMMON'", $source);
        $this->assertStringContainsString("WHEN r.rarity_percent > 0.2 THEN 'RARE'", $source);
        $this->assertStringContainsString("WHEN r.rarity_percent > 0.02 THEN 'EPIC'", $source);
        $this->assertStringContainsString("ELSE 'LEGENDARY'", $source);
        $this->assertStringContainsString("WHEN r.in_game_rarity_percent <= 1 THEN 'LEGENDARY'", $source);
    }

    public function testRankedOwnerTempTableUsesPrimaryKeyForLookup(): void
    {
        $source = $this->readPrivateConstant('CREATE_RANKED_OWNER_TEMP_TABLE_QUERY');

        $this->assertStringContainsString('CREATE TEMPORARY TABLE tmp_daily_ranked_trophy_owners', $source);
        $this->assertStringContainsString('PRIMARY KEY (np_communication_id, order_id)', $source);
        $this->assertStringContainsString('order_id SMALLINT UNSIGNED NOT NULL', $source);
    }

    public function testDailyCronAvoidsPerTitleTrophyEarnedProbes(): void
    {
        $source = $this->readClassSource();

        $this->assertStringContainsString('prepareAndPopulateRankedOwnerCounts', $source);
        $this->assertStringContainsString('prepareRankedOwnerTempTables', $source);
        $this->assertStringContainsString('populateRankedOwnerCounts', $source);
        $this->assertStringContainsString('applyTrophyRarityFromTemporaryTable', $source);
        $this->assertStringContainsString('RANKED_OWNER_SNAPSHOT_BATCH_SIZE', $source);
        $this->assertStringContainsString('RANKED_OWNER_BATCH_DELAY_SECONDS', $source);
        $this->assertStringContainsString('tmp_daily_ranked_players', $source);
        $this->assertStringContainsString('countRankedPlayerSnapshot', $source);
        $this->assertFalse(str_contains($source, 'SELECT np_communication_id FROM trophy_title'));
        $this->assertFalse(str_contains($source, 'JSON_TABLE('));
        $this->assertFalse(str_contains($source, 'RANKED_OWNER_TITLE_BATCH_SIZE'));
        $this->assertFalse(str_contains($source, 'RANKED_OWNER_RANKING_BATCH_SIZE'));
        $this->assertFalse(str_contains($source, 'RANKED_OWNER_ACCOUNT_BATCH_SIZE'));
        $this->assertFalse(str_contains($source, 'fetchTopTenThousandOwnerTitleLookup'));
        $this->assertFalse(str_contains($source, 'UPDATE_TROPHY_RARITY_BATCH_QUERY'));
        $this->assertFalse(str_contains($source, 'UPDATE_TROPHY_RARITY_ZERO_OWNERS_QUERY'));
    }

    public function testUpdateTrophyTitleRarityPointsAggregatesPerGame(): void
    {
        $source = $this->readPrivateConstant('UPDATE_TITLE_RARITY_POINTS_QUERY');

        $this->assertStringContainsString('IFNULL(SUM(tm.rarity_point), 0) AS rarity_sum', $source);
        $this->assertStringContainsString('IFNULL(SUM(tm.in_game_rarity_point), 0) AS in_game_rarity_sum', $source);
        $this->assertStringContainsString('GROUP BY t.np_communication_id', $source);
        $this->assertStringContainsString('ttm.rarity_points = r.rarity_sum', $source);
        $this->assertStringContainsString('ttm.in_game_rarity_points = r.in_game_rarity_sum', $source);
    }

    public function testExecuteWithRetryUsesInfiniteLoopWithoutMaxAttemptCap(): void
    {
        $source = $this->readClassSource();

        $this->assertStringContainsString('while (true)', $source);
        $this->assertFalse(str_contains($source, 'maxAttempt'));
        $this->assertStringContainsString('prepareAndPopulateRankedOwnerCounts', $source);
        $this->assertStringContainsString('applyTrophyRarityFromTemporaryTableWithRecovery', $source);
        $this->assertStringContainsString('preparePopulateAndApplyRankedOwnerRarity', $source);
        $this->assertStringContainsString('isMissingRankedOwnerTempTableError', $source);
        $this->assertStringContainsString('updateTrophyTitleRarityPoints', $source);
    }

    public function testRunBuildsTempOwnerCountsAppliesRarityThenTitlePoints(): void
    {
        $database = new DailyCronJobHappyPathTestDatabase();

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds): void {
                throw new RuntimeException('Sleeper should not be called.');
            },
            rankedOwnerSnapshotBatchSize: 10000,
            batchDelaySeconds: 0,
        );

        $job->run();

        $this->assertSame([
            'drop-owners-temp',
            'drop-players-temp',
            'create-players-temp',
            'create-owners-temp',
            'snapshot-players',
            'count-snapshot',
            'populate-owners',
            'apply-rarity',
            'drop-owners-temp',
            'drop-players-temp',
            'title-points',
        ], $database->getOperations());
    }

    public function testPopulateRankedOwnerCountsUsesSnapshotRowBatchesAndYieldsBetweenThem(): void
    {
        $database = new DailyCronJobRankingBatchTestDatabase();
        $sleepCalls = [];

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
            rankedOwnerSnapshotBatchSize: 2500,
            batchDelaySeconds: 1,
        );

        $job->run();

        $this->assertSame([
            [1, 2500],
            [2501, 5000],
            [5001, 7500],
            [7501, 10000],
        ], $database->getSnapshotBatches());
        $this->assertSame([1, 1, 1], $sleepCalls);
        $this->assertSame([
            'drop-owners-temp',
            'drop-players-temp',
            'create-players-temp',
            'create-owners-temp',
            'snapshot-players',
            'count-snapshot',
            'populate-owners',
            'populate-owners',
            'populate-owners',
            'populate-owners',
            'apply-rarity',
            'drop-owners-temp',
            'drop-players-temp',
            'title-points',
        ], $database->getOperations());
    }

    public function testRunRetriesRarityRebuildUntilItSucceeds(): void
    {
        $database = new DailyCronJobRarityRetryTestDatabase();
        $sleepCalls = [];

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
            rankedOwnerSnapshotBatchSize: 10000,
            batchDelaySeconds: 0,
        );

        $job->run();

        $this->assertSame([1, 1], $sleepCalls);
        $this->assertSame(3, $database->getPopulateCount());
        $this->assertSame(1, $database->getApplyCount());
        $this->assertSame(1, $database->getTitlePointsUpdateCount());
    }

    public function testRunRetriesApplyWithoutRepopulatingRankedOwnerCounts(): void
    {
        $database = new DailyCronJobApplyRetryTestDatabase();
        $sleepCalls = [];

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
            rankedOwnerSnapshotBatchSize: 10000,
            batchDelaySeconds: 0,
        );

        $job->run();

        $this->assertSame([1, 1], $sleepCalls);
        $this->assertSame(1, $database->getPopulateCount());
        $this->assertSame(3, $database->getApplyCount());
        $this->assertSame(1, $database->getTitlePointsUpdateCount());
    }

    public function testRunRebuildsRankedOwnerCountsWhenApplyLosesTempTable(): void
    {
        $database = new DailyCronJobMissingTempTableRecoveryTestDatabase();
        $sleepCalls = [];

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
            rankedOwnerSnapshotBatchSize: 10000,
            batchDelaySeconds: 0,
        );

        $job->run();

        $this->assertSame([], $sleepCalls);
        $this->assertSame(2, $database->getPopulateCount());
        $this->assertSame(2, $database->getApplyCount());
        $this->assertSame(1, $database->getTitlePointsUpdateCount());
        $this->assertSame([
            'drop-owners-temp',
            'drop-players-temp',
            'create-players-temp',
            'create-owners-temp',
            'snapshot-players',
            'count-snapshot',
            'populate-owners',
            'apply-missing-temp',
            'drop-owners-temp',
            'drop-players-temp',
            'create-players-temp',
            'create-owners-temp',
            'snapshot-players',
            'count-snapshot',
            'populate-owners',
            'apply-rarity',
            'drop-owners-temp',
            'drop-players-temp',
            'title-points',
        ], $database->getOperations());
    }

    public function testRunRetriesTitleRarityPointAggregationUntilItSucceeds(): void
    {
        $database = new DailyCronJobTitleRetryTestDatabase();
        $sleepCalls = [];

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
            rankedOwnerSnapshotBatchSize: 10000,
            batchDelaySeconds: 0,
        );

        $job->run();

        $this->assertSame([1, 1], $sleepCalls);
        $this->assertSame(1, $database->getPopulateCount());
        $this->assertSame(1, $database->getApplyCount());
        $this->assertSame(3, $database->getTitlePointsUpdateCount());
    }

    public function testRunDropsTempTableAfterRarityRebuildFailureBeforeRetry(): void
    {
        $database = new DailyCronJobTempCleanupRetryTestDatabase();
        $sleepCalls = [];

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
            rankedOwnerSnapshotBatchSize: 10000,
            batchDelaySeconds: 0,
        );

        $job->run();

        $this->assertSame([1], $sleepCalls);
        $this->assertSame([
            'drop-owners-temp',
            'drop-players-temp',
            'create-players-temp',
            'create-owners-temp',
            'snapshot-players',
            'count-snapshot',
            'populate-owners',
            'drop-owners-temp',
            'drop-players-temp',
            'drop-owners-temp',
            'drop-players-temp',
            'create-players-temp',
            'create-owners-temp',
            'snapshot-players',
            'count-snapshot',
            'populate-owners',
            'apply-rarity',
            'drop-owners-temp',
            'drop-players-temp',
            'title-points',
        ], $database->getOperations());
    }

    public function testRunDoesNotApplyEmptyTempTableAfterFailedRecoveryPopulate(): void
    {
        $database = new DailyCronJobFailedRecoveryPopulateTestDatabase();
        $sleepCalls = [];

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
            rankedOwnerSnapshotBatchSize: 10000,
            batchDelaySeconds: 0,
        );

        $job->run();

        $this->assertSame([1], $sleepCalls);
        $this->assertSame(3, $database->getPopulateCount());
        $this->assertSame(2, $database->getApplyCount());
        $this->assertSame(1, $database->getTitlePointsUpdateCount());
        $this->assertSame([
            'drop-owners-temp',
            'drop-players-temp',
            'create-players-temp',
            'create-owners-temp',
            'snapshot-players',
            'count-snapshot',
            'populate-owners',
            'apply-missing-temp',
            'drop-owners-temp',
            'drop-players-temp',
            'create-players-temp',
            'create-owners-temp',
            'snapshot-players',
            'count-snapshot',
            'populate-owners-failed',
            'drop-owners-temp',
            'drop-players-temp',
            'drop-owners-temp',
            'drop-players-temp',
            'create-players-temp',
            'create-owners-temp',
            'snapshot-players',
            'count-snapshot',
            'populate-owners',
            'apply-rarity',
            'drop-owners-temp',
            'drop-players-temp',
            'title-points',
        ], $database->getOperations());
    }

    public function testRunDoesNotApplyEmptyTempTableWhenRecoveryCleanupDropFails(): void
    {
        $database = new DailyCronJobFailedRecoveryCleanupDropTestDatabase();
        $sleepCalls = [];

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
            rankedOwnerSnapshotBatchSize: 10000,
            batchDelaySeconds: 0,
        );

        $job->run();

        $this->assertSame([1], $sleepCalls);
        $this->assertSame(3, $database->getPopulateCount());
        $this->assertSame(2, $database->getApplyCount());
        $this->assertSame(1, $database->getTitlePointsUpdateCount());
        $this->assertFalse($database->didApplyWhileCountsUnready());
        $this->assertSame([
            'drop-owners-temp',
            'drop-players-temp',
            'create-players-temp',
            'create-owners-temp',
            'snapshot-players',
            'count-snapshot',
            'populate-owners',
            'apply-missing-temp',
            'drop-owners-temp',
            'drop-players-temp',
            'create-players-temp',
            'create-owners-temp',
            'snapshot-players',
            'count-snapshot',
            'populate-owners-failed',
            'drop-owners-temp-failed',
            'drop-owners-temp',
            'drop-players-temp',
            'create-players-temp',
            'create-owners-temp',
            'snapshot-players',
            'count-snapshot',
            'populate-owners',
            'apply-rarity',
            'drop-owners-temp',
            'drop-players-temp',
            'title-points',
        ], $database->getOperations());
    }

    public function testRunContinuesToTitlePointsWhenFinalTempTableDropFails(): void
    {
        $database = new DailyCronJobFinalDropFailureTestDatabase();

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds): void {
                throw new RuntimeException('Sleeper should not be called.');
            },
            rankedOwnerSnapshotBatchSize: 10000,
            batchDelaySeconds: 0,
        );

        $job->run();

        $this->assertSame([
            'drop-owners-temp',
            'drop-players-temp',
            'create-players-temp',
            'create-owners-temp',
            'snapshot-players',
            'count-snapshot',
            'populate-owners',
            'apply-rarity',
            'drop-owners-temp-failed',
            'title-points',
        ], $database->getOperations());
        $this->assertSame(1, $database->getTitlePointsUpdateCount());
    }

    private function readClassSource(): string
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/classes/Cron/DailyCronJob.php');
        $this->assertTrue(is_string($source));

        return $source;
    }

    private function readPrivateConstant(string $name): string
    {
        $class = new ReflectionClass(DailyCronJob::class);
        $constant = $class->getReflectionConstant($name);
        $this->assertTrue($constant instanceof ReflectionClassConstant);
        $value = $constant->getValue();
        $this->assertTrue(is_string($value));

        return $value;
    }
}

final class DailyCronJobTempTableTestSupport
{
    /**
     * @param list<string> $operations
     */
    public static function recordExec(array &$operations, string $statement): ?int
    {
        if (str_contains($statement, 'DROP TEMPORARY TABLE IF EXISTS tmp_daily_ranked_trophy_owners')) {
            $operations[] = 'drop-owners-temp';

            return 0;
        }

        if (str_contains($statement, 'DROP TEMPORARY TABLE IF EXISTS tmp_daily_ranked_players')) {
            $operations[] = 'drop-players-temp';

            return 0;
        }

        if (str_contains($statement, 'CREATE TEMPORARY TABLE tmp_daily_ranked_players')) {
            $operations[] = 'create-players-temp';

            return 0;
        }

        if (str_contains($statement, 'CREATE TEMPORARY TABLE tmp_daily_ranked_trophy_owners')) {
            $operations[] = 'create-owners-temp';

            return 0;
        }

        return null;
    }

    public static function isSnapshotPopulateQuery(string $query): bool
    {
        return str_contains($query, 'INSERT INTO tmp_daily_ranked_players');
    }

    public static function isSnapshotCountQuery(string $query): bool
    {
        return str_contains($query, 'SELECT COUNT(*) FROM tmp_daily_ranked_players');
    }

    /**
     * @param list<string> $operations
     */
    public static function createSnapshotCountStatement(array &$operations, int $count = 10000): DailyCronJobTestStatement
    {
        return new DailyCronJobTestStatement(
            static function () use (&$operations): void {
                $operations[] = 'count-snapshot';
            },
            fetchColumnValue: $count,
        );
    }

    public static function createSilentSnapshotCountStatement(int $count = 10000): DailyCronJobTestStatement
    {
        return new DailyCronJobTestStatement(
            static function (): void {
            },
            fetchColumnValue: $count,
        );
    }
}

final class DailyCronJobHappyPathTestDatabase extends PDO
{
    /** @var list<string> */
    private array $operations = [];

    public function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function exec(string $statement): int|false
    {
        $result = DailyCronJobTempTableTestSupport::recordExec($this->operations, $statement);
        if ($result !== null) {
            return $result;
        }

        throw new RuntimeException('Unexpected exec call: ' . $statement);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (DailyCronJobTempTableTestSupport::isSnapshotPopulateQuery($query)) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'snapshot-players';
            });
        }

        if (DailyCronJobTempTableTestSupport::isSnapshotCountQuery($query)) {
            return DailyCronJobTempTableTestSupport::createSnapshotCountStatement($this->operations);
        }

        if (str_contains($query, 'INSERT INTO tmp_daily_ranked_trophy_owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'populate-owners';
            });
        }

        if (str_contains($query, 'LEFT JOIN tmp_daily_ranked_trophy_owners owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'apply-rarity';
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'title-points';
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobRankingBatchTestDatabase extends PDO
{
    /** @var list<string> */
    private array $operations = [];

    /** @var list<array{0: int, 1: int}> */
    private array $snapshotBatches = [];

    public function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    public function getSnapshotBatches(): array
    {
        return $this->snapshotBatches;
    }

    public function exec(string $statement): int|false
    {
        $result = DailyCronJobTempTableTestSupport::recordExec($this->operations, $statement);
        if ($result !== null) {
            return $result;
        }

        throw new RuntimeException('Unexpected exec call: ' . $statement);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (DailyCronJobTempTableTestSupport::isSnapshotPopulateQuery($query)) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'snapshot-players';
            });
        }

        if (DailyCronJobTempTableTestSupport::isSnapshotCountQuery($query)) {
            return DailyCronJobTempTableTestSupport::createSnapshotCountStatement($this->operations);
        }

        if (str_contains($query, 'INSERT INTO tmp_daily_ranked_trophy_owners')) {
            return new DailyCronJobTestStatement(function (?array $params, array $boundValues): void {
                $this->operations[] = 'populate-owners';
                $this->snapshotBatches[] = [
                    (int) $boundValues[':min_position'],
                    (int) $boundValues[':max_position'],
                ];
            });
        }

        if (str_contains($query, 'LEFT JOIN tmp_daily_ranked_trophy_owners owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'apply-rarity';
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'title-points';
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobRarityRetryTestDatabase extends PDO
{
    private int $populateCount = 0;

    private int $applyCount = 0;

    private int $titlePointsUpdateCount = 0;

    public function __construct()
    {
    }

    public function getPopulateCount(): int
    {
        return $this->populateCount;
    }

    public function getApplyCount(): int
    {
        return $this->applyCount;
    }

    public function getTitlePointsUpdateCount(): int
    {
        return $this->titlePointsUpdateCount;
    }

    public function exec(string $statement): int|false
    {
        return 0;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (DailyCronJobTempTableTestSupport::isSnapshotPopulateQuery($query)) {
            return new DailyCronJobTestStatement(static function (): void {
            });
        }

        if (DailyCronJobTempTableTestSupport::isSnapshotCountQuery($query)) {
            return DailyCronJobTempTableTestSupport::createSilentSnapshotCountStatement();
        }

        if (str_contains($query, 'INSERT INTO tmp_daily_ranked_trophy_owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->populateCount++;
                if ($this->populateCount < 3) {
                    throw new RuntimeException('Simulated populate failure.');
                }
            });
        }

        if (str_contains($query, 'LEFT JOIN tmp_daily_ranked_trophy_owners owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->applyCount++;
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->titlePointsUpdateCount++;
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobApplyRetryTestDatabase extends PDO
{
    private int $populateCount = 0;

    private int $applyCount = 0;

    private int $titlePointsUpdateCount = 0;

    public function __construct()
    {
    }

    public function getPopulateCount(): int
    {
        return $this->populateCount;
    }

    public function getApplyCount(): int
    {
        return $this->applyCount;
    }

    public function getTitlePointsUpdateCount(): int
    {
        return $this->titlePointsUpdateCount;
    }

    public function exec(string $statement): int|false
    {
        return 0;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (DailyCronJobTempTableTestSupport::isSnapshotPopulateQuery($query)) {
            return new DailyCronJobTestStatement(static function (): void {
            });
        }

        if (DailyCronJobTempTableTestSupport::isSnapshotCountQuery($query)) {
            return DailyCronJobTempTableTestSupport::createSilentSnapshotCountStatement();
        }

        if (str_contains($query, 'INSERT INTO tmp_daily_ranked_trophy_owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->populateCount++;
            });
        }

        if (str_contains($query, 'LEFT JOIN tmp_daily_ranked_trophy_owners owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->applyCount++;
                if ($this->applyCount < 3) {
                    throw new RuntimeException('Simulated apply rarity failure.');
                }
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->titlePointsUpdateCount++;
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobTitleRetryTestDatabase extends PDO
{
    private int $populateCount = 0;

    private int $applyCount = 0;

    private int $titlePointsUpdateCount = 0;

    public function __construct()
    {
    }

    public function getPopulateCount(): int
    {
        return $this->populateCount;
    }

    public function getApplyCount(): int
    {
        return $this->applyCount;
    }

    public function getTitlePointsUpdateCount(): int
    {
        return $this->titlePointsUpdateCount;
    }

    public function exec(string $statement): int|false
    {
        return 0;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (DailyCronJobTempTableTestSupport::isSnapshotPopulateQuery($query)) {
            return new DailyCronJobTestStatement(static function (): void {
            });
        }

        if (DailyCronJobTempTableTestSupport::isSnapshotCountQuery($query)) {
            return DailyCronJobTempTableTestSupport::createSilentSnapshotCountStatement();
        }

        if (str_contains($query, 'INSERT INTO tmp_daily_ranked_trophy_owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->populateCount++;
            });
        }

        if (str_contains($query, 'LEFT JOIN tmp_daily_ranked_trophy_owners owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->applyCount++;
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->titlePointsUpdateCount++;
                if ($this->titlePointsUpdateCount < 3) {
                    throw new RuntimeException('Simulated title rarity point aggregation failure.');
                }
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobTempCleanupRetryTestDatabase extends PDO
{
    /** @var list<string> */
    private array $operations = [];

    private int $populateCount = 0;

    public function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function exec(string $statement): int|false
    {
        $result = DailyCronJobTempTableTestSupport::recordExec($this->operations, $statement);
        if ($result !== null) {
            return $result;
        }

        throw new RuntimeException('Unexpected exec call: ' . $statement);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (DailyCronJobTempTableTestSupport::isSnapshotPopulateQuery($query)) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'snapshot-players';
            });
        }

        if (DailyCronJobTempTableTestSupport::isSnapshotCountQuery($query)) {
            return DailyCronJobTempTableTestSupport::createSnapshotCountStatement($this->operations);
        }

        if (str_contains($query, 'INSERT INTO tmp_daily_ranked_trophy_owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->populateCount++;
                $this->operations[] = 'populate-owners';
                if ($this->populateCount === 1) {
                    throw new RuntimeException('Simulated populate failure.');
                }
            });
        }

        if (str_contains($query, 'LEFT JOIN tmp_daily_ranked_trophy_owners owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'apply-rarity';
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'title-points';
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobFailedRecoveryCleanupDropTestDatabase extends PDO
{
    /** @var list<string> */
    private array $operations = [];

    private int $populateCount = 0;

    private int $applyCount = 0;

    private int $titlePointsUpdateCount = 0;

    private int $dropCount = 0;

    private bool $countsReady = false;

    private bool $appliedWhileCountsUnready = false;

    public function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getPopulateCount(): int
    {
        return $this->populateCount;
    }

    public function getApplyCount(): int
    {
        return $this->applyCount;
    }

    public function getTitlePointsUpdateCount(): int
    {
        return $this->titlePointsUpdateCount;
    }

    public function didApplyWhileCountsUnready(): bool
    {
        return $this->appliedWhileCountsUnready;
    }

    public function exec(string $statement): int|false
    {
        if (str_contains($statement, 'DROP TEMPORARY TABLE IF EXISTS tmp_daily_ranked_trophy_owners')) {
            $this->dropCount++;
            // First recovery cleanup DROP after failed populate leaves the empty table.
            if ($this->populateCount === 2 && $this->dropCount === 3) {
                $this->operations[] = 'drop-owners-temp-failed';
                throw new RuntimeException('Simulated recovery cleanup drop failure.');
            }

            $this->operations[] = 'drop-owners-temp';
            $this->countsReady = false;

            return 0;
        }

        if (str_contains($statement, 'DROP TEMPORARY TABLE IF EXISTS tmp_daily_ranked_players')) {
            $this->operations[] = 'drop-players-temp';

            return 0;
        }

        if (str_contains($statement, 'CREATE TEMPORARY TABLE tmp_daily_ranked_players')) {
            $this->operations[] = 'create-players-temp';
            $this->countsReady = false;

            return 0;
        }

        if (str_contains($statement, 'CREATE TEMPORARY TABLE tmp_daily_ranked_trophy_owners')) {
            $this->operations[] = 'create-owners-temp';
            $this->countsReady = false;

            return 0;
        }

        throw new RuntimeException('Unexpected exec call: ' . $statement);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (DailyCronJobTempTableTestSupport::isSnapshotPopulateQuery($query)) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'snapshot-players';
            });
        }

        if (DailyCronJobTempTableTestSupport::isSnapshotCountQuery($query)) {
            return DailyCronJobTempTableTestSupport::createSnapshotCountStatement($this->operations);
        }

        if (str_contains($query, 'INSERT INTO tmp_daily_ranked_trophy_owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->populateCount++;
                if ($this->populateCount === 2) {
                    $this->operations[] = 'populate-owners-failed';
                    $this->countsReady = false;
                    throw new RuntimeException('Simulated recovery populate failure.');
                }

                $this->operations[] = 'populate-owners';
                $this->countsReady = true;
            });
        }

        if (str_contains($query, 'LEFT JOIN tmp_daily_ranked_trophy_owners owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->applyCount++;
                if (!$this->countsReady) {
                    $this->appliedWhileCountsUnready = true;
                }

                if ($this->applyCount === 1) {
                    $this->operations[] = 'apply-missing-temp';
                    throw new RuntimeException(
                        "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'psn100.tmp_daily_ranked_trophy_owners' doesn't exist"
                    );
                }

                $this->operations[] = 'apply-rarity';
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'title-points';
                $this->titlePointsUpdateCount++;
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobFailedRecoveryPopulateTestDatabase extends PDO
{
    /** @var list<string> */
    private array $operations = [];

    private int $populateCount = 0;

    private int $applyCount = 0;

    private int $titlePointsUpdateCount = 0;

    public function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getPopulateCount(): int
    {
        return $this->populateCount;
    }

    public function getApplyCount(): int
    {
        return $this->applyCount;
    }

    public function getTitlePointsUpdateCount(): int
    {
        return $this->titlePointsUpdateCount;
    }

    public function exec(string $statement): int|false
    {
        $result = DailyCronJobTempTableTestSupport::recordExec($this->operations, $statement);
        if ($result !== null) {
            return $result;
        }

        throw new RuntimeException('Unexpected exec call: ' . $statement);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (DailyCronJobTempTableTestSupport::isSnapshotPopulateQuery($query)) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'snapshot-players';
            });
        }

        if (DailyCronJobTempTableTestSupport::isSnapshotCountQuery($query)) {
            return DailyCronJobTempTableTestSupport::createSnapshotCountStatement($this->operations);
        }

        if (str_contains($query, 'INSERT INTO tmp_daily_ranked_trophy_owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->populateCount++;
                if ($this->populateCount === 2) {
                    $this->operations[] = 'populate-owners-failed';
                    throw new RuntimeException('Simulated recovery populate failure.');
                }

                $this->operations[] = 'populate-owners';
            });
        }

        if (str_contains($query, 'LEFT JOIN tmp_daily_ranked_trophy_owners owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->applyCount++;
                // Fail until a successful recovery populate has completed (populateCount === 3).
                if ($this->populateCount < 3) {
                    $this->operations[] = 'apply-missing-temp';
                    throw new RuntimeException(
                        "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'psn100.tmp_daily_ranked_trophy_owners' doesn't exist"
                    );
                }

                $this->operations[] = 'apply-rarity';
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'title-points';
                $this->titlePointsUpdateCount++;
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobMissingTempTableRecoveryTestDatabase extends PDO
{
    /** @var list<string> */
    private array $operations = [];

    private int $populateCount = 0;

    private int $applyCount = 0;

    private int $titlePointsUpdateCount = 0;

    public function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getPopulateCount(): int
    {
        return $this->populateCount;
    }

    public function getApplyCount(): int
    {
        return $this->applyCount;
    }

    public function getTitlePointsUpdateCount(): int
    {
        return $this->titlePointsUpdateCount;
    }

    public function exec(string $statement): int|false
    {
        $result = DailyCronJobTempTableTestSupport::recordExec($this->operations, $statement);
        if ($result !== null) {
            return $result;
        }

        throw new RuntimeException('Unexpected exec call: ' . $statement);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (DailyCronJobTempTableTestSupport::isSnapshotPopulateQuery($query)) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'snapshot-players';
            });
        }

        if (DailyCronJobTempTableTestSupport::isSnapshotCountQuery($query)) {
            return DailyCronJobTempTableTestSupport::createSnapshotCountStatement($this->operations);
        }

        if (str_contains($query, 'INSERT INTO tmp_daily_ranked_trophy_owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->populateCount++;
                $this->operations[] = 'populate-owners';
            });
        }

        if (str_contains($query, 'LEFT JOIN tmp_daily_ranked_trophy_owners owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->applyCount++;
                if ($this->applyCount === 1) {
                    $this->operations[] = 'apply-missing-temp';
                    throw new RuntimeException(
                        "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'psn100.tmp_daily_ranked_trophy_owners' doesn't exist"
                    );
                }

                $this->operations[] = 'apply-rarity';
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'title-points';
                $this->titlePointsUpdateCount++;
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobFinalDropFailureTestDatabase extends PDO
{
    /** @var list<string> */
    private array $operations = [];

    private int $ownersDropCount = 0;

    private int $titlePointsUpdateCount = 0;

    public function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getTitlePointsUpdateCount(): int
    {
        return $this->titlePointsUpdateCount;
    }

    public function exec(string $statement): int|false
    {
        if (str_contains($statement, 'DROP TEMPORARY TABLE IF EXISTS tmp_daily_ranked_trophy_owners')) {
            $this->ownersDropCount++;
            if ($this->ownersDropCount === 1) {
                // Initial prepare cleanup before create.
                $this->operations[] = 'drop-owners-temp';

                return 0;
            }

            $this->operations[] = 'drop-owners-temp-failed';
            throw new RuntimeException('Simulated final temp table drop failure.');
        }

        if (str_contains($statement, 'DROP TEMPORARY TABLE IF EXISTS tmp_daily_ranked_players')) {
            $this->operations[] = 'drop-players-temp';

            return 0;
        }

        if (str_contains($statement, 'CREATE TEMPORARY TABLE tmp_daily_ranked_players')) {
            $this->operations[] = 'create-players-temp';

            return 0;
        }

        if (str_contains($statement, 'CREATE TEMPORARY TABLE tmp_daily_ranked_trophy_owners')) {
            $this->operations[] = 'create-owners-temp';

            return 0;
        }

        throw new RuntimeException('Unexpected exec call: ' . $statement);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (DailyCronJobTempTableTestSupport::isSnapshotPopulateQuery($query)) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'snapshot-players';
            });
        }

        if (DailyCronJobTempTableTestSupport::isSnapshotCountQuery($query)) {
            return DailyCronJobTempTableTestSupport::createSnapshotCountStatement($this->operations);
        }

        if (str_contains($query, 'INSERT INTO tmp_daily_ranked_trophy_owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'populate-owners';
            });
        }

        if (str_contains($query, 'LEFT JOIN tmp_daily_ranked_trophy_owners owners')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'apply-rarity';
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->operations[] = 'title-points';
                $this->titlePointsUpdateCount++;
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobTestStatement extends PDOStatement
{
    /** @var callable */
    private $callback;

    private bool $isSelect;

    private mixed $fetchColumnValue;

    /** @var array<string|int, mixed> */
    private array $boundValues = [];

    /**
     * @param callable $callback
     */
    public function __construct(callable $callback, bool $isSelect = false, mixed $fetchColumnValue = null)
    {
        $this->callback = $callback;
        $this->isSelect = $isSelect;
        $this->fetchColumnValue = $fetchColumnValue;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->boundValues[$param] = $value;

        return true;
    }

    public function execute(?array $params = null): bool
    {
        if (!$this->isSelect) {
            ($this->callback)($params, $this->boundValues);
        }

        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if (!$this->isSelect) {
            return [];
        }

        $result = ($this->callback)();

        return is_array($result) ? $result : [];
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->fetchColumnValue;
    }
}
