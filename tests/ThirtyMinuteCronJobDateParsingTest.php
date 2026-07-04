<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyHistoryRecorder.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/ThirtyMinuteCronJob.php';

final class ThirtyMinuteCronJobDateParsingTest extends TestCase
{
    private PDO $database;
    private ThirtyMinuteCronJob $cronJob;
    private ReflectionMethod $gameTimestampsMatchMethod;
    private ReflectionMethod $formatDateTimeForDatabaseMethod;
    private ReflectionMethod $determineScanStartIndexMethod;
    private ReflectionMethod $ensureValidTrophyTitleLastUpdatedDateMethod;

    protected function setUp(): void
    {
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

        $this->gameTimestampsMatchMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'gameTimestampsMatch');
        $this->gameTimestampsMatchMethod->setAccessible(true);

        $this->formatDateTimeForDatabaseMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'formatDateTimeForDatabase');
        $this->formatDateTimeForDatabaseMethod->setAccessible(true);

        $this->determineScanStartIndexMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'determineScanStartIndex');
        $this->determineScanStartIndexMethod->setAccessible(true);

        $this->ensureValidTrophyTitleLastUpdatedDateMethod = new ReflectionMethod(
            ThirtyMinuteCronJob::class,
            'ensureValidTrophyTitleLastUpdatedDate'
        );
        $this->ensureValidTrophyTitleLastUpdatedDateMethod->setAccessible(true);
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

    public function testGameTimestampsMatchReturnsFalseWhenSonyTimestampIsInvalid(): void
    {
        $result = $this->gameTimestampsMatchMethod->invoke(
            $this->cronJob,
            'not-a-valid-date',
            'not-a-valid-date'
        );

        $this->assertFalse($result);
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

    public function testGameTimestampsMatchReturnsFalseWhenDatabaseTimestampIsInvalid(): void
    {
        $result = $this->gameTimestampsMatchMethod->invoke(
            $this->cronJob,
            '2024-06-15T10:30:00Z',
            'not-a-valid-date'
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

    public function testDetermineScanStartIndexRescansWhenInvalidTimestampsMatch(): void
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

        $this->assertSame(0, $result);
    }

    public function testEnsureValidTrophyTitleLastUpdatedDateReturnsTitleWhenDateIsValid(): void
    {
        $title = new ThirtyMinuteCronJobDateParsingTestTrophyTitle('NPWR12345_00', '2024-06-15T10:30:00Z', 'Example Game');
        $user = new ThirtyMinuteCronJobDateParsingTestUser([]);

        $result = $this->ensureValidTrophyTitleLastUpdatedDateMethod->invoke(
            $this->cronJob,
            $user,
            $title,
            'ExampleUser'
        );

        $this->assertSame($title, $result);
        $this->assertSame(0, $user->getFetchCount());
    }

    public function testEnsureValidTrophyTitleLastUpdatedDateRefetchesSonyDataWhenDateIsInvalid(): void
    {
        $invalidTitle = new ThirtyMinuteCronJobDateParsingTestTrophyTitle('NPWR12345_00', 'not-a-valid-date', 'Example Game');
        $validTitle = new ThirtyMinuteCronJobDateParsingTestTrophyTitle('NPWR12345_00', '2024-06-15T10:30:00Z', 'Example Game');
        $user = new ThirtyMinuteCronJobDateParsingTestUser([
            [new ThirtyMinuteCronJobDateParsingTestTrophyTitle('NPWR12345_00', '2024-06-15T10:30:00Z', 'Example Game')],
        ]);

        $result = $this->ensureValidTrophyTitleLastUpdatedDateMethod->invoke(
            $this->cronJob,
            $user,
            $invalidTitle,
            'ExampleUser'
        );

        $this->assertSame('2024-06-15T10:30:00Z', $result->lastUpdatedDateTime());
        $this->assertSame(1, $user->getFetchCount());
        $this->assertSame($validTitle->lastUpdatedDateTime(), $result->lastUpdatedDateTime());
    }

    public function testEnsureValidTrophyTitleLastUpdatedDateReturnsNullWhenRefetchStillInvalid(): void
    {
        $invalidTitle = new ThirtyMinuteCronJobDateParsingTestTrophyTitle('NPWR12345_00', 'not-a-valid-date', 'Example Game');
        $user = new ThirtyMinuteCronJobDateParsingTestUser([
            [new ThirtyMinuteCronJobDateParsingTestTrophyTitle('NPWR12345_00', 'still-not-valid', 'Example Game')],
        ]);

        $result = $this->ensureValidTrophyTitleLastUpdatedDateMethod->invoke(
            $this->cronJob,
            $user,
            $invalidTitle,
            'ExampleUser'
        );

        $this->assertSame(null, $result);
        $this->assertSame(1, $user->getFetchCount());

        $logMessage = $this->database->query('SELECT message FROM log ORDER BY rowid DESC LIMIT 1')->fetchColumn();
        $this->assertStringContainsString('invalid last updated date', (string) $logMessage);
    }
}

final class ThirtyMinuteCronJobDateParsingTestTrophyTitle
{
    public function __construct(
        private readonly string $npCommunicationId,
        private readonly string $lastUpdatedDateTime,
        private readonly string $name = ''
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

    public function name(): string
    {
        return $this->name;
    }
}

final class ThirtyMinuteCronJobDateParsingTestUser
{
    /** @var list<list<ThirtyMinuteCronJobDateParsingTestTrophyTitle>> */
    private array $fetchResults;
    private int $fetchCount = 0;

    /**
     * @param list<list<ThirtyMinuteCronJobDateParsingTestTrophyTitle>> $fetchResults
     */
    public function __construct(array $fetchResults)
    {
        $this->fetchResults = $fetchResults;
    }

    public function trophyTitles(): ThirtyMinuteCronJobDateParsingTestTrophyTitleCollection
    {
        $titles = $this->fetchResults[$this->fetchCount] ?? $this->fetchResults[array_key_last($this->fetchResults)] ?? [];
        $this->fetchCount++;

        return new ThirtyMinuteCronJobDateParsingTestTrophyTitleCollection($titles);
    }

    public function getFetchCount(): int
    {
        return $this->fetchCount;
    }
}

final class ThirtyMinuteCronJobDateParsingTestTrophyTitleCollection implements IteratorAggregate
{
    /** @param list<ThirtyMinuteCronJobDateParsingTestTrophyTitle> $titles */
    public function __construct(private readonly array $titles)
    {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->titles);
    }
}
