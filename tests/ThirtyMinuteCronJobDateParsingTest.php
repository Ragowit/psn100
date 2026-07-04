<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyHistoryRecorder.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/ThirtyMinuteCronJob.php';

final class ThirtyMinuteCronJobDateParsingTest extends TestCase
{
    private ThirtyMinuteCronJob $cronJob;
    private ReflectionMethod $gameTimestampsMatchMethod;
    private ReflectionMethod $formatDateTimeForDatabaseMethod;
    private ReflectionMethod $determineScanStartIndexMethod;

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

        $this->gameTimestampsMatchMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'gameTimestampsMatch');
        $this->gameTimestampsMatchMethod->setAccessible(true);

        $this->formatDateTimeForDatabaseMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'formatDateTimeForDatabase');
        $this->formatDateTimeForDatabaseMethod->setAccessible(true);

        $this->determineScanStartIndexMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'determineScanStartIndex');
        $this->determineScanStartIndexMethod->setAccessible(true);
    }

    public function testGameTimestampsMatchReturnsTrueForMatchingValidDates(): void
    {
        $result = $this->gameTimestampsMatchMethod->invoke(
            $this->cronJob,
            '2024-06-15T10:30:00Z',
            '2024-06-15 10:30:00'
        );

        $this->assertTrue($result);
    }

    public function testGameTimestampsMatchReturnsFalseForDifferentValidDates(): void
    {
        $result = $this->gameTimestampsMatchMethod->invoke(
            $this->cronJob,
            '2024-06-15T10:30:00Z',
            '2024-06-16 10:30:00'
        );

        $this->assertFalse($result);
    }

    public function testGameTimestampsMatchReturnsTrueWhenBothInvalidButSameRawString(): void
    {
        $invalidTimestamp = 'not-a-valid-date';

        $result = $this->gameTimestampsMatchMethod->invoke(
            $this->cronJob,
            $invalidTimestamp,
            $invalidTimestamp
        );

        $this->assertTrue($result);
    }

    public function testGameTimestampsMatchReturnsFalseWhenBothInvalidButDifferentRawStrings(): void
    {
        $result = $this->gameTimestampsMatchMethod->invoke(
            $this->cronJob,
            'not-a-valid-date',
            'also-not-valid'
        );

        $this->assertFalse($result);
    }

    public function testFormatDateTimeForDatabaseReturnsFormattedSonyTimestamp(): void
    {
        $result = $this->formatDateTimeForDatabaseMethod->invoke(
            $this->cronJob,
            '2024-06-15T10:30:00Z'
        );

        $this->assertSame('2024-06-15 10:30:00', $result);
    }

    public function testFormatDateTimeForDatabaseReturnsNullForInvalidTimestamp(): void
    {
        $result = $this->formatDateTimeForDatabaseMethod->invoke(
            $this->cronJob,
            'not-a-valid-date'
        );

        $this->assertSame(null, $result);
    }

    public function testFormatDateTimeForDatabaseReturnsNullForEmptyString(): void
    {
        $result = $this->formatDateTimeForDatabaseMethod->invoke(
            $this->cronJob,
            ''
        );

        $this->assertSame(null, $result);
    }

    public function testDetermineScanStartIndexRescansWhenInvalidTimestampsDiffer(): void
    {
        $trophyTitles = [
            new ThirtyMinuteCronJobDateParsingTestTrophyTitle('NPWR12345_00', 'not-a-valid-date'),
        ];

        $gameLastUpdatedDate = [
            'NPWR12345_00' => 'also-not-valid',
        ];

        $result = $this->determineScanStartIndexMethod->invoke(
            $this->cronJob,
            $trophyTitles,
            $gameLastUpdatedDate
        );

        $this->assertSame(0, $result);
    }

    public function testDetermineScanStartIndexSkipsWhenInvalidTimestampsMatch(): void
    {
        $invalidTimestamp = 'not-a-valid-date';
        $trophyTitles = [
            new ThirtyMinuteCronJobDateParsingTestTrophyTitle('NPWR12345_00', $invalidTimestamp),
        ];

        $gameLastUpdatedDate = [
            'NPWR12345_00' => $invalidTimestamp,
        ];

        $result = $this->determineScanStartIndexMethod->invoke(
            $this->cronJob,
            $trophyTitles,
            $gameLastUpdatedDate
        );

        $this->assertSame(1, $result);
    }
}

final class ThirtyMinuteCronJobDateParsingTestTrophyTitle
{
    public function __construct(
        private readonly string $npCommunicationId,
        private readonly string $lastUpdatedDateTime
    ) {
    }

    public function npCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function lastUpdatedDateTime(): string
    {
        return $this->lastUpdatedDateTime;
    }
}
