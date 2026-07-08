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
    private ReflectionMethod $determineScanStartIndexMethod;
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

        $this->determineScanStartIndexMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'determineScanStartIndex');
        $this->determineScanStartIndexMethod->setAccessible(true);

        $this->handleInvalidTitleLastUpdatedDateResponseMethod = new ReflectionMethod(
            ThirtyMinuteCronJob::class,
            'handleInvalidTitleLastUpdatedDateResponse'
        );
        $this->handleInvalidTitleLastUpdatedDateResponseMethod->setAccessible(true);
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
