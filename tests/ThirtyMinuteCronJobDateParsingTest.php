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
    private ReflectionMethod $formatSonyDateTimeForDatabaseMethod;
    private ReflectionMethod $determineScanStartIndexMethod;
    private ReflectionMethod $ensureValidTrophyTitleLastUpdatedDateMethod;
    private ReflectionMethod $shouldRetryInvalidTitleLastUpdatedDateMethod;
    private ReflectionMethod $handleInvalidTitleLastUpdatedDateResponseMethod;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE log (message TEXT NOT NULL)');
        $this->database->exec('CREATE TABLE player_queue (online_id TEXT PRIMARY KEY)');
        $this->database->exec('CREATE TABLE player (account_id INTEGER PRIMARY KEY, online_id TEXT NOT NULL, last_updated_date TEXT)');
        $this->database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY, scanning TEXT, scan_progress TEXT)');
        $this->database->exec("INSERT INTO setting (id, scanning, scan_progress) VALUES (1, 'ExampleUser', NULL)");

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

        $this->formatSonyDateTimeForDatabaseMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'formatSonyDateTimeForDatabase');
        $this->formatSonyDateTimeForDatabaseMethod->setAccessible(true);

        $this->determineScanStartIndexMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'determineScanStartIndex');
        $this->determineScanStartIndexMethod->setAccessible(true);

        $this->ensureValidTrophyTitleLastUpdatedDateMethod = new ReflectionMethod(
            ThirtyMinuteCronJob::class,
            'ensureValidTrophyTitleLastUpdatedDate'
        );
        $this->ensureValidTrophyTitleLastUpdatedDateMethod->setAccessible(true);

        $this->shouldRetryInvalidTitleLastUpdatedDateMethod = new ReflectionMethod(
            ThirtyMinuteCronJob::class,
            'shouldRetryInvalidTitleLastUpdatedDate'
        );
        $this->shouldRetryInvalidTitleLastUpdatedDateMethod->setAccessible(true);

        $this->handleInvalidTitleLastUpdatedDateResponseMethod = new ReflectionMethod(
            ThirtyMinuteCronJob::class,
            'handleInvalidTitleLastUpdatedDateResponse'
        );
        $this->handleInvalidTitleLastUpdatedDateResponseMethod->setAccessible(true);
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

    public function testFormatSonyDateTimeForDatabaseReturnsFormattedSonyTimestamp(): void
    {
        $result = $this->formatSonyDateTimeForDatabaseMethod->invoke(
            $this->cronJob,
            '2024-06-15T10:30:00Z'
        );

        $this->assertSame('2024-06-15 10:30:00', $result);
    }

    public function testFormatSonyDateTimeForDatabaseReturnsNullForInvalidTimestamp(): void
    {
        $result = $this->formatSonyDateTimeForDatabaseMethod->invoke(
            $this->cronJob,
            'not-a-valid-date'
        );

        $this->assertSame(null, $result);
    }

    public function testFormatSonyDateTimeForDatabaseReturnsNullForEmptyString(): void
    {
        $result = $this->formatSonyDateTimeForDatabaseMethod->invoke(
            $this->cronJob,
            ''
        );

        $this->assertSame(null, $result);
    }

    public function testFormatSonyDateTimeForDatabaseRejectsLenientDatabaseStyleTimestamp(): void
    {
        $result = $this->formatSonyDateTimeForDatabaseMethod->invoke(
            $this->cronJob,
            '2024-06-15 10:30:00'
        );

        $this->assertSame(null, $result);
    }

    public function testFormatSonyDateTimeForDatabaseRejectsInvalidCalendarDate(): void
    {
        $result = $this->formatSonyDateTimeForDatabaseMethod->invoke(
            $this->cronJob,
            '2024-02-30T10:30:00Z'
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

    public function testShouldRetryInvalidTitleLastUpdatedDateReturnsTrueOnFirstAttempt(): void
    {
        $retryTracker = [];

        $result = $this->shouldRetryInvalidTitleLastUpdatedDateMethod->invoke(
            $this->cronJob,
            $retryTracker,
            'ExampleUser',
            'NPWR12345_00'
        );

        $this->assertTrue($result);
    }

    public function testShouldRetryInvalidTitleLastUpdatedDateReturnsFalseAfterMarkedRetried(): void
    {
        $retryTracker = [
            'ExampleUser:title:NPWR12345_00' => true,
        ];

        $result = $this->shouldRetryInvalidTitleLastUpdatedDateMethod->invoke(
            $this->cronJob,
            $retryTracker,
            'ExampleUser',
            'NPWR12345_00'
        );

        $this->assertFalse($result);
    }

    public function testHandleInvalidTitleLastUpdatedDateResponseDefersPlayerScan(): void
    {
        $this->database->exec("INSERT INTO player_queue (online_id) VALUES ('ExampleUser')");

        $this->handleInvalidTitleLastUpdatedDateResponseMethod->invoke(
            $this->cronJob,
            ['online_id' => 'ExampleUser'],
            1,
            'NPWR12345_00'
        );

        $queueCount = $this->database->query('SELECT COUNT(*) FROM player_queue')->fetchColumn();
        $this->assertSame(0, (int) $queueCount);

        $setting = $this->database->query('SELECT scanning, scan_progress FROM setting WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertTrue(is_array($setting));
        $this->assertSame('1', (string) $setting['scanning']);
        $this->assertSame(null, $setting['scan_progress']);

        $logMessage = $this->database->query('SELECT message FROM log ORDER BY rowid DESC LIMIT 1')->fetchColumn();
        $this->assertStringContainsString('NPWR12345_00', (string) $logMessage);
    }

    public function testIsValidTrophyEarnedDateTimeAcceptsEmptyTimestamp(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'isValidTrophyEarnedDateTime');
        $method->setAccessible(true);

        $trophy = new ThirtyMinuteCronJobDateParsingTestTrophy(1, '', true);

        $this->assertTrue($method->invoke($this->cronJob, $trophy));
    }

    public function testIsValidTrophyEarnedDateTimeRejectsMalformedTimestamp(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'isValidTrophyEarnedDateTime');
        $method->setAccessible(true);

        $trophy = new ThirtyMinuteCronJobDateParsingTestTrophy(1, 'not-a-valid-date', true);

        $this->assertFalse($method->invoke($this->cronJob, $trophy));
    }

    public function testEnsureValidTrophyEarnedDateRefetchesSonyDataWhenDateIsInvalid(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'ensureValidTrophyEarnedDate');
        $method->setAccessible(true);

        $invalidTrophy = new ThirtyMinuteCronJobDateParsingTestTrophy(1, 'not-a-valid-date', true);
        $validTrophy = new ThirtyMinuteCronJobDateParsingTestTrophy(1, '2024-06-15T10:30:00Z', true);
        $trophyGroup = new ThirtyMinuteCronJobDateParsingTestTrophyGroup([
            [new ThirtyMinuteCronJobDateParsingTestTrophy(1, '2024-06-15T10:30:00Z', true)],
        ]);

        $result = $method->invoke(
            $this->cronJob,
            $trophyGroup,
            $invalidTrophy,
            'ExampleUser',
            'NPWR12345_00'
        );

        $this->assertSame('2024-06-15T10:30:00Z', $result->earnedDateTime());
        $this->assertSame(1, $trophyGroup->getFetchCount());
        $this->assertSame($validTrophy->earnedDateTime(), $result->earnedDateTime());
    }

    public function testEnsureValidTrophyEarnedDateReturnsUpdatedEarnedAndProgressValues(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'ensureValidTrophyEarnedDate');
        $method->setAccessible(true);

        $invalidTrophy = new ThirtyMinuteCronJobDateParsingTestTrophy(1, 'not-a-valid-date', true, '');
        $trophyGroup = new ThirtyMinuteCronJobDateParsingTestTrophyGroup([
            [new ThirtyMinuteCronJobDateParsingTestTrophy(1, '2024-06-15T10:30:00Z', false, '75')],
        ]);

        $result = $method->invoke(
            $this->cronJob,
            $trophyGroup,
            $invalidTrophy,
            'ExampleUser',
            'NPWR12345_00'
        );

        $this->assertFalse($result->earned());
        $this->assertSame('75', $result->progress());
    }

    public function testIsTrophyEarnedTreatsNullApiValueAsFalse(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'isTrophyEarned');
        $method->setAccessible(true);

        $trophyWithNullEarned = new ThirtyMinuteCronJobDateParsingTestCachedTrophy(['earned' => null]);
        $trophyWithMissingEarned = new ThirtyMinuteCronJobDateParsingTestCachedTrophy([]);
        $trophyWithTrueEarned = new ThirtyMinuteCronJobDateParsingTestCachedTrophy(['earned' => true]);
        $trophyWithFalseEarned = new ThirtyMinuteCronJobDateParsingTestCachedTrophy(['earned' => false]);
        $legacyTrophy = new ThirtyMinuteCronJobDateParsingTestTrophy(1, '', true);

        $this->assertFalse($method->invoke($this->cronJob, $trophyWithNullEarned));
        $this->assertFalse($method->invoke($this->cronJob, $trophyWithMissingEarned));
        $this->assertTrue($method->invoke($this->cronJob, $trophyWithTrueEarned));
        $this->assertFalse($method->invoke($this->cronJob, $trophyWithFalseEarned));
        $this->assertTrue($method->invoke($this->cronJob, $legacyTrophy));
    }

    public function testIsTrophyEarnedTreatsTypeErrorFromEarnedAccessorAsFalse(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'isTrophyEarned');
        $method->setAccessible(true);

        $trophy = new ThirtyMinuteCronJobDateParsingTestTypeErrorTrophy();

        $this->assertFalse($method->invoke($this->cronJob, $trophy));
    }

    public function testShouldRetryInvalidTrophyEarnedDateReturnsFalseAfterMarkedRetried(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'shouldRetryInvalidTrophyEarnedDate');
        $method->setAccessible(true);

        $retryTracker = [
            'ExampleUser:earned:NPWR12345_00:001:1' => true,
        ];

        $result = $method->invoke(
            $this->cronJob,
            $retryTracker,
            'ExampleUser',
            'NPWR12345_00',
            '001',
            1
        );

        $this->assertFalse($result);
    }

    public function testHandleInvalidTrophyEarnedDateResponseDefersPlayerScan(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'handleInvalidTrophyEarnedDateResponse');
        $method->setAccessible(true);

        $this->database->exec("INSERT INTO player_queue (online_id) VALUES ('ExampleUser')");

        $method->invoke(
            $this->cronJob,
            ['online_id' => 'ExampleUser'],
            1,
            'NPWR12345_00',
            '001',
            1
        );

        $queueCount = $this->database->query('SELECT COUNT(*) FROM player_queue')->fetchColumn();
        $this->assertSame(0, (int) $queueCount);

        $logMessage = $this->database->query('SELECT message FROM log ORDER BY rowid DESC LIMIT 1')->fetchColumn();
        $this->assertStringContainsString('invalid earned date', (string) $logMessage);
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

final class ThirtyMinuteCronJobDateParsingTestTrophy
{
    public function __construct(
        private readonly int $id,
        private readonly string $earnedDateTime,
        private readonly bool $earned,
        private readonly string $progress = ''
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function earnedDateTime(): string
    {
        return $this->earnedDateTime;
    }

    public function earned(): bool
    {
        return $this->earned;
    }

    public function progress(): string
    {
        return $this->progress;
    }
}

final class ThirtyMinuteCronJobDateParsingTestCachedTrophy
{
    /**
     * @param array<string, mixed> $cache
     */
    public function __construct(private readonly array $cache)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getCache(): array
    {
        return $this->cache;
    }
}

final class ThirtyMinuteCronJobDateParsingTestTypeErrorTrophy
{
    public function earned(): bool
    {
        throw new TypeError('Return value must be of type bool, null returned');
    }
}

final class ThirtyMinuteCronJobDateParsingTestTrophyGroup
{
    /** @var list<list<ThirtyMinuteCronJobDateParsingTestTrophy>> */
    private array $fetchResults;
    private int $fetchCount = 0;

    /**
     * @param list<list<ThirtyMinuteCronJobDateParsingTestTrophy>> $fetchResults
     */
    public function __construct(array $fetchResults)
    {
        $this->fetchResults = $fetchResults;
    }

    public function id(): string
    {
        return '001';
    }

    /**
     * @return list<ThirtyMinuteCronJobDateParsingTestTrophy>
     */
    public function trophies(): array
    {
        $titles = $this->fetchResults[$this->fetchCount] ?? $this->fetchResults[array_key_last($this->fetchResults)] ?? [];
        $this->fetchCount++;

        return $titles;
    }

    public function getFetchCount(): int
    {
        return $this->fetchCount;
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
