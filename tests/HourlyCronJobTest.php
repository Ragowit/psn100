<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/HourlyCronJob.php';

final class HourlyCronJobTest extends TestCase
{
    public function testUsesBatchAndStatsTemporaryTablesForNonEmptyBatchUpdates(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);
        $createBatchTableQuery = $this->readPrivateConstantValue($class, 'CREATE_BATCH_TEMP_TABLE_QUERY');
        $createStatsTableQuery = $this->readPrivateConstantValue($class, 'CREATE_STATS_TEMP_TABLE_QUERY');
        $populateBatchStatsQuery = $this->readPrivateConstantValue($class, 'POPULATE_BATCH_STATS_QUERY');
        $updateMetaQuery = $this->readPrivateConstantValue($class, 'UPDATE_META_QUERY');

        $this->assertStringContainsString('COLLATE utf8mb4_0900_ai_ci', $createBatchTableQuery);
        $this->assertStringContainsString('COLLATE utf8mb4_0900_ai_ci', $createStatsTableQuery);
        $this->assertStringContainsString('INSERT INTO tmp_hourly_stats', $populateBatchStatsQuery);
        $this->assertStringContainsString('FROM trophy_title_player ttp', $populateBatchStatsQuery);
        $this->assertStringContainsString('JOIN tmp_hourly_batch b ON b.np_communication_id = ttp.np_communication_id', $populateBatchStatsQuery);
        $this->assertStringContainsString('JOIN player_ranking pr ON pr.account_id = ttp.account_id AND pr.ranking <= 10000', $populateBatchStatsQuery);
        $this->assertStringContainsString('COUNT(*) AS owners', $populateBatchStatsQuery);
        $this->assertStringContainsString('SUM(ttp.progress = 100) AS owners_completed', $populateBatchStatsQuery);
        $this->assertStringContainsString('SUM(ttp.last_updated_date >= (UTC_TIMESTAMP() - INTERVAL 7 DAY)) AS recent_players', $populateBatchStatsQuery);

        $this->assertStringContainsString('UPDATE trophy_title_meta ttm', $updateMetaQuery);
        $this->assertStringContainsString('JOIN tmp_hourly_batch b ON b.np_communication_id = ttm.np_communication_id', $updateMetaQuery);
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
        $this->assertStringContainsString('DELETE FROM tmp_hourly_stats', $source);
        $this->assertFalse(str_contains($source, 'TRUNCATE TABLE tmp_hourly_batch'));
        $this->assertFalse(str_contains($source, 'TRUNCATE TABLE tmp_hourly_stats'));
    }

    public function testPopulatesStatisticsPerBatch(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);

        $this->assertFalse($class->hasMethod('populateAllStatistics'));

        $batchSource = $this->readMethodSource($class, 'updateStatisticsForBatch');
        $this->assertStringContainsString('POPULATE_BATCH_STATS_QUERY', $batchSource);
        $this->assertStringContainsString('DELETE FROM tmp_hourly_stats', $batchSource);

        $updateSource = $this->readMethodSource($class, 'updateTrophyTitleStatistics');
        $this->assertFalse(str_contains($updateSource, 'populateAllStatistics'));
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

    private function readPrivateConstantValue(ReflectionClass $class, string $name): string
    {
        $constant = $class->getReflectionConstant($name);
        $this->assertTrue($constant instanceof ReflectionClassConstant);
        $value = $constant->getValue();
        $this->assertTrue(is_string($value));

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
