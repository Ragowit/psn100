<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyHistoryRecorder.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/ThirtyMinuteCronJob.php';

final class ThirtyMinuteCronJobDeletionGuardTest extends TestCase
{
    private ThirtyMinuteCronJob $cronJob;
    private ReflectionMethod $guardMethod;

    protected function setUp(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $trophyCalculator = new TrophyCalculator($database);
        $logger = new Psn100Logger($database);
        $historyRecorder = new TrophyHistoryRecorder($database, $logger);

        $this->cronJob = new ThirtyMinuteCronJob($database, $trophyCalculator, $logger, $historyRecorder, 1);

        $this->guardMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'shouldDeleteMissingZeroPercentGames');
        $this->guardMethod->setAccessible(true);
    }

    public function testDeletionIsSkippedWhenSonyReturnsNoGames(): void
    {
        $shouldDelete = $this->guardMethod->invoke($this->cronJob, 0, 120, []);

        $this->assertFalse($shouldDelete);
    }

    public function testDeletionProceedsWhenCountsDifferAndSonyReturnedGames(): void
    {
        $shouldDelete = $this->guardMethod->invoke($this->cronJob, 3, 5, ['N0001', 'N0002', 'N0003']);

        $this->assertTrue($shouldDelete);
    }

    public function testDeletionSkippedWhenCountsMatch(): void
    {
        $shouldDelete = $this->guardMethod->invoke($this->cronJob, 4, 4, ['N0001', 'N0002', 'N0003', 'N0004']);

        $this->assertFalse($shouldDelete);
    }
}
