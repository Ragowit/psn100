<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/HourlyCronJob.php';

final class HourlyCronJobTest extends TestCase
{
    public function testPrecomputesAllStatisticsOnceBeforeBatchUpdates(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);
        $createBatchTableQuery = $this->readPrivateConstantValue($class, 'CREATE_BATCH_TEMP_TABLE_QUERY');
        $createStatsTableQuery = $this->readPrivateConstantValue($class, 'CREATE_STATS_TEMP_TABLE_QUERY');
        $populateAllStatsQuery = $this->readPrivateConstantValue($class, 'POPULATE_ALL_STATS_QUERY');
        $updateMetaQuery = $this->readPrivateConstantValue($class, 'UPDATE_META_QUERY');

        $this->assertStringContainsString('COLLATE utf8mb4_0900_ai_ci', $createBatchTableQuery);
        $this->assertStringContainsString('COLLATE utf8mb4_0900_ai_ci', $createStatsTableQuery);
        $this->assertStringContainsString('INSERT INTO tmp_hourly_stats', $populateAllStatsQuery);
        $this->assertStringContainsString('FROM trophy_title_player ttp', $populateAllStatsQuery);
        $this->assertStringContainsString('JOIN tmp_hourly_ranked_players rp ON rp.account_id = ttp.account_id', $populateAllStatsQuery);
        $this->assertFalse(str_contains($populateAllStatsQuery, 'tmp_hourly_batch'));
        $this->assertFalse(str_contains($populateAllStatsQuery, 'player_ranking'));
        $this->assertStringContainsString('COUNT(*) AS owners', $populateAllStatsQuery);
        $this->assertStringContainsString('SUM(ttp.progress = 100) AS owners_completed', $populateAllStatsQuery);
        $this->assertStringContainsString('SUM(ttp.last_updated_date >= (UTC_TIMESTAMP() - INTERVAL 7 DAY)) AS recent_players', $populateAllStatsQuery);

        $this->assertStringContainsString('UPDATE trophy_title_meta ttm', $updateMetaQuery);
        $this->assertStringContainsString('JOIN tmp_hourly_batch b ON b.np_communication_id = ttm.np_communication_id', $updateMetaQuery);
    }

    public function testRankedPlayerSnapshotFreezesTopTenThousandFromPlayerRanking(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);
        $create = $this->readPrivateConstantValue($class, 'CREATE_RANKED_PLAYER_SNAPSHOT_QUERY');
        $populate = $this->readPrivateConstantValue($class, 'POPULATE_RANKED_PLAYER_SNAPSHOT_QUERY');
        $source = file_get_contents((string) $class->getFileName());
        $this->assertTrue(is_string($source));

        $this->assertStringContainsString('CREATE TEMPORARY TABLE tmp_hourly_ranked_players', $create);
        $this->assertStringContainsString('PRIMARY KEY (account_id)', $create);
        $this->assertStringContainsString('KEY idx_tmp_hourly_ranked_players_ranking (ranking, account_id)', $create);
        $this->assertStringContainsString(
            'INSERT INTO tmp_hourly_ranked_players (account_id, ranking)',
            $populate,
        );
        $this->assertStringContainsString('FROM player_ranking pr FORCE INDEX (idx_pr_ranking_account)', $populate);
        $this->assertStringContainsString('WHERE pr.ranking <= 10000', $populate);
        $this->assertSame(10000, $this->readPrivateConstantInt($class, 'TOP_RANKED_PLAYERS'));

        $initSource = $this->readMethodSource($class, 'initializeTemporaryTables');
        $this->assertStringContainsString('prepareRankedPlayerSnapshot', $initSource);

        $prepareSource = $this->readMethodSource($class, 'prepareRankedPlayerSnapshot');
        $this->assertStringContainsString('DROP TEMPORARY TABLE IF EXISTS tmp_hourly_ranked_players', $prepareSource);
        $this->assertStringContainsString('CREATE_RANKED_PLAYER_SNAPSHOT_QUERY', $prepareSource);
        $this->assertStringContainsString('POPULATE_RANKED_PLAYER_SNAPSHOT_QUERY', $prepareSource);

        $this->assertStringContainsString('DROP TEMPORARY TABLE IF EXISTS tmp_hourly_ranked_players', $source);
    }

    public function testResetsBatchTitlesWithoutQualifyingPlayersToZeroValues(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);
        $updateMetaQuery = $this->readPrivateConstantValue($class, 'UPDATE_META_QUERY');

        $this->assertStringContainsString('LEFT JOIN tmp_hourly_stats s ON s.np_communication_id = ttm.np_communication_id', $updateMetaQuery);
        $this->assertStringContainsString('ttm.owners = COALESCE(s.owners, 0)', $updateMetaQuery);
        $this->assertStringContainsString('ttm.owners_completed = COALESCE(s.owners_completed, 0)', $updateMetaQuery);
        $this->assertStringContainsString('ttm.recent_players = COALESCE(s.recent_players, 0)', $updateMetaQuery);
        $this->assertStringContainsString('COALESCE(s.owners, 0) = 0', $updateMetaQuery);
        $this->assertStringContainsString('(COALESCE(s.owners_completed, 0) / COALESCE(s.owners, 0)) * 100', $updateMetaQuery);
    }

    public function testUsesDeleteInsteadOfTruncateInBatchUpdateFlow(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);
        $source = file_get_contents((string) $class->getFileName());
        $this->assertTrue(is_string($source));

        $this->assertStringContainsString('DELETE FROM tmp_hourly_batch', $source);
        $this->assertFalse(str_contains($source, 'DELETE FROM tmp_hourly_stats'));
        $this->assertFalse(str_contains($source, 'TRUNCATE TABLE tmp_hourly_batch'));
        $this->assertFalse(str_contains($source, 'TRUNCATE TABLE tmp_hourly_stats'));
    }

    public function testPopulatesAllStatisticsOnceBeforeBatchUpdates(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);

        $this->assertTrue($class->hasMethod('populateAllStatistics'));
        $this->assertFalse($class->hasConstant('POPULATE_BATCH_STATS_QUERY'));

        $batchSource = $this->readMethodSource($class, 'updateStatisticsForBatch');
        $this->assertFalse(str_contains($batchSource, 'POPULATE_ALL_STATS_QUERY'));
        $this->assertFalse(str_contains($batchSource, 'DELETE FROM tmp_hourly_stats'));

        $runSource = $this->readMethodSource($class, 'run');
        $this->assertStringContainsString('prepareAndPopulateStatistics', $runSource);
        $this->assertStringContainsString('applyStatisticsInBatchesWithRecovery', $runSource);

        $prepareSource = $this->readMethodSource($class, 'prepareAndPopulateStatistics');
        $this->assertStringContainsString('populateAllStatistics', $prepareSource);

        $applySource = $this->readMethodSource($class, 'applyStatisticsInBatches');
        $this->assertStringContainsString('while (true)', $applySource);
        $this->assertFalse(str_contains($applySource, 'populateAllStatistics'));
    }

    public function testPopulateAndApplyRetryIndependently(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);
        $runSource = $this->readMethodSource($class, 'run');

        $this->assertStringContainsString(
            '$this->executeWithRetry($this->prepareAndPopulateStatistics(...));',
            $runSource,
        );
        $this->assertStringContainsString(
            '$this->executeWithRetry($this->applyStatisticsInBatchesWithRecovery(...));',
            $runSource,
        );

        $recoverySource = $this->readMethodSource($class, 'applyStatisticsInBatchesWithRecovery');
        $this->assertStringContainsString('isMissingTempTableError', $recoverySource);
        $this->assertStringContainsString('preparePopulateAndApplyStatistics', $recoverySource);
    }

    public function testIsMissingTempTableErrorDetectsHourlyTempTables(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);
        $method = $class->getMethod('isMissingTempTableError');
        $job = $class->newInstanceWithoutConstructor();

        $this->assertTrue($method->invoke(
            $job,
            new RuntimeException("Table 'psn100.tmp_hourly_stats' doesn't exist")
        ));
        $this->assertTrue($method->invoke(
            $job,
            new RuntimeException('Base table or view not found: tmp_hourly_batch')
        ));
        $this->assertTrue($method->invoke(
            $job,
            new RuntimeException("Temporary table tmp_hourly_ranked_players does not exist")
        ));
        $this->assertFalse($method->invoke(
            $job,
            new RuntimeException('Lock wait timeout exceeded; try restarting transaction')
        ));
        $this->assertFalse($method->invoke(
            $job,
            new RuntimeException("Table 'psn100.trophy_title_meta' doesn't exist")
        ));
    }

    public function testNoDynamicUnionAllSelectBatchPathExists(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);
        $source = file_get_contents((string) $class->getFileName());
        $this->assertTrue(is_string($source));

        $this->assertFalse($class->hasMethod('buildBatchUnionQuery'));
        $this->assertFalse(str_contains($source, 'UNION ALL'));
        $this->assertFalse(str_contains($source, 'SELECT ? AS np_communication_id'));
    }

    public function testDoesNotUseSingleSetBasedUpdateForAllTitles(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);
        $source = file_get_contents((string) $class->getFileName());
        $this->assertTrue(is_string($source));

        $this->assertFalse($class->hasConstant('UPDATE_ALL_META_QUERY'));
        $this->assertStringContainsString('BATCH_SIZE', $source);
    }

    public function testFailedPopulateDropsTempTablesBeforeRethrow(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);
        $prepareSource = $this->readMethodSource($class, 'prepareAndPopulateStatistics');

        $this->assertStringContainsString('dropTemporaryTables', $prepareSource);
        $this->assertStringContainsString('throw $exception', $prepareSource);
    }

    private function readPrivateConstantValue(ReflectionClass $class, string $name): string
    {
        $constant = $class->getReflectionConstant($name);
        $this->assertTrue($constant instanceof ReflectionClassConstant);
        $value = $constant->getValue();
        $this->assertTrue(is_string($value));

        return $value;
    }

    private function readPrivateConstantInt(ReflectionClass $class, string $name): int
    {
        $constant = $class->getReflectionConstant($name);
        $this->assertTrue($constant instanceof ReflectionClassConstant);
        $value = $constant->getValue();
        $this->assertTrue(is_int($value));

        return $value;
    }

    private function readMethodSource(ReflectionClass $class, string $methodName): string
    {
        $method = $class->getMethod($methodName);
        $source = file_get_contents((string) $class->getFileName());
        $this->assertTrue(is_string($source));

        $lines = explode("\n", $source);
        $startLine = $method->getStartLine() - 1;
        $lineCount = $method->getEndLine() - $method->getStartLine() + 1;

        return implode("\n", array_slice($lines, $startLine, $lineCount));
    }
}
