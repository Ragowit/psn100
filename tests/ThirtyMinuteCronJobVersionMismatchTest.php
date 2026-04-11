<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/ThirtyMinuteCronJob.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyHistoryRecorder.php';

final class ThirtyMinuteCronJobVersionMismatchTest extends TestCase
{
    private PDO $database;
    private ThirtyMinuteCronJob $cronJob;
    private ReflectionMethod $versionGuardMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE log (message TEXT NOT NULL)');

        $logger = new Psn100Logger($this->database);
        $this->cronJob = new ThirtyMinuteCronJob(
            $this->database,
            new TrophyCalculator($this->database),
            $logger,
            new TrophyHistoryRecorder($this->database, $logger),
            1
        );

        $this->versionGuardMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'shouldSkipMetadataSyncForVersionMismatch');
        $this->versionGuardMethod->setAccessible(true);
    }

    public function testVersionMismatchSkipsMetadataSyncAndLogsContext(): void
    {
        $shouldSkip = $this->versionGuardMethod->invoke(
            $this->cronJob,
            'PlayerOne',
            'Example Trophy Title',
            'NPWR99999_00',
            ' 01.00 ',
            "\t02.00\n"
        );

        $this->assertTrue($shouldSkip);

        $message = (string) $this->database->query('SELECT message FROM log LIMIT 1')->fetchColumn();
        $this->assertStringContainsString('PlayerOne', $message);
        $this->assertStringContainsString('Example Trophy Title', $message);
        $this->assertStringContainsString('NPWR99999_00', $message);
        $this->assertStringContainsString('title version "01.00"', $message);
        $this->assertStringContainsString('lookup version "02.00"', $message);
    }

    public function testMissingOrMatchingVersionsDoNotSkipMetadataSync(): void
    {
        $this->assertFalse($this->versionGuardMethod->invoke(
            $this->cronJob,
            'PlayerOne',
            'Example Trophy Title',
            'NPWR99999_00',
            ' 01.00 ',
            '01.00'
        ));

        $this->assertFalse($this->versionGuardMethod->invoke(
            $this->cronJob,
            'PlayerOne',
            'Example Trophy Title',
            'NPWR99999_00',
            ' ',
            '02.00'
        ));

        $count = (int) $this->database->query('SELECT COUNT(*) FROM log')->fetchColumn();
        $this->assertSame(0, $count);
    }
}
