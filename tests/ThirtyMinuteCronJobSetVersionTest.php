<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyHistoryRecorder.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/ThirtyMinuteCronJob.php';

final class ThirtyMinuteCronJobSetVersionTest extends TestCase
{
    private ThirtyMinuteCronJob $cronJob;

    protected function setUp(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE log (message TEXT NOT NULL)');

        $logger = new Psn100Logger($database);
        $this->cronJob = new ThirtyMinuteCronJob(
            $database,
            new TrophyCalculator($database),
            $logger,
            new TrophyHistoryRecorder($database, $logger),
            1
        );
    }

    public function testResolveSetVersionForUpdateKeepsCurrentWhenNewVersionIsLower(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'resolveSetVersionForUpdate');
        $method->setAccessible(true);

        $result = $method->invoke($this->cronJob, '01.09', '01.10');

        $this->assertSame('01.10', $result);
    }

    public function testResolveSetVersionForUpdateAllowsEqualOrHigherVersion(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'resolveSetVersionForUpdate');
        $method->setAccessible(true);

        $this->assertSame('01.10', $method->invoke($this->cronJob, '01.10', '01.10'));
        $this->assertSame('01.11', $method->invoke($this->cronJob, '01.11', '01.10'));
    }

    public function testResolveSetVersionForUpdateUsesNewVersionWhenCurrentMissing(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'resolveSetVersionForUpdate');
        $method->setAccessible(true);

        $result = $method->invoke($this->cronJob, '01.05', null);

        $this->assertSame('01.05', $result);
    }


    public function testIsIncomingSetVersionOlderThanStoredReturnsTrueForLowerVersion(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'isIncomingSetVersionOlderThanStored');
        $method->setAccessible(true);

        $result = $method->invoke($this->cronJob, '01.09', '01.10');

        $this->assertTrue($result);
    }

    public function testIsIncomingSetVersionOlderThanStoredReturnsFalseForEqualOrHigherVersion(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'isIncomingSetVersionOlderThanStored');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($this->cronJob, '01.10', '01.10'));
        $this->assertFalse($method->invoke($this->cronJob, '01.11', '01.10'));
    }
}
