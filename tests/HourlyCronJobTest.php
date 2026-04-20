<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/HourlyCronJob.php';

final class HourlyCronJobTest extends TestCase
{
    public function testUsesSingleSetBasedUpdateQuery(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);
        $updateAllMetaQuery = $this->readPrivateConstantValue($class, 'UPDATE_ALL_META_QUERY');

        $this->assertStringContainsString('UPDATE trophy_title_meta ttm', $updateAllMetaQuery);
        $this->assertStringContainsString('LEFT JOIN (', $updateAllMetaQuery);
        $this->assertStringContainsString('FROM trophy_title_player ttp', $updateAllMetaQuery);
        $this->assertStringContainsString('JOIN player_ranking pr ON pr.account_id = ttp.account_id', $updateAllMetaQuery);
        $this->assertStringContainsString('WHERE pr.ranking <= 10000', $updateAllMetaQuery);
        $this->assertStringContainsString('GROUP BY ttp.np_communication_id', $updateAllMetaQuery);
        $this->assertStringContainsString('COUNT(*) AS owners', $updateAllMetaQuery);
        $this->assertStringContainsString('SUM(ttp.progress = 100) AS owners_completed', $updateAllMetaQuery);
        $this->assertStringContainsString('SUM(ttp.last_updated_date >= (UTC_TIMESTAMP() - INTERVAL 7 DAY)) AS recent_players', $updateAllMetaQuery);

        $this->assertFalse(str_contains($updateAllMetaQuery, 'tmp_hourly_batch'));
        $this->assertFalse(str_contains($updateAllMetaQuery, 'tmp_hourly_stats'));
    }

    public function testResetsTitlesWithoutQualifyingPlayersToZeroValues(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);
        $updateAllMetaQuery = $this->readPrivateConstantValue($class, 'UPDATE_ALL_META_QUERY');

        $this->assertStringContainsString('LEFT JOIN (', $updateAllMetaQuery);
        $this->assertStringContainsString(') s ON s.np_communication_id = ttm.np_communication_id', $updateAllMetaQuery);
        $this->assertStringContainsString('ttm.owners = COALESCE(s.owners, 0)', $updateAllMetaQuery);
        $this->assertStringContainsString('ttm.owners_completed = COALESCE(s.owners_completed, 0)', $updateAllMetaQuery);
        $this->assertStringContainsString('ttm.recent_players = COALESCE(s.recent_players, 0)', $updateAllMetaQuery);
        $this->assertStringContainsString('COALESCE(s.owners, 0) = 0', $updateAllMetaQuery);
        $this->assertStringContainsString('(COALESCE(s.owners_completed, 0) / COALESCE(s.owners, 0)) * 100', $updateAllMetaQuery);
    }


    public function testDoesNotUseTempTableBatchFlow(): void
    {
        $class = new ReflectionClass(HourlyCronJob::class);
        $source = file_get_contents((string) $class->getFileName());
        $this->assertTrue(is_string($source));

        $this->assertFalse(str_contains($source, 'tmp_hourly_batch'));
        $this->assertFalse(str_contains($source, 'tmp_hourly_stats'));
        $this->assertFalse(str_contains($source, 'TRUNCATE TABLE'));
        $this->assertFalse($class->hasConstant('CREATE_BATCH_TEMP_TABLE_QUERY'));
        $this->assertFalse($class->hasConstant('CREATE_STATS_TEMP_TABLE_QUERY'));
        $this->assertFalse($class->hasConstant('INSERT_STATS_QUERY'));
        $this->assertFalse($class->hasConstant('UPDATE_META_QUERY'));
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

    private function readPrivateConstantValue(ReflectionClass $class, string $name): string
    {
        $constant = $class->getReflectionConstant($name);
        $this->assertTrue($constant instanceof ReflectionClassConstant);
        $value = $constant->getValue();
        $this->assertTrue(is_string($value));

        return $value;
    }
}
