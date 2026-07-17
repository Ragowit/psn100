<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyHistoryRecorder.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyMergeService.php';
require_once __DIR__ . '/../wwwroot/classes/AutomaticTrophyTitleMergeService.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/WorkerScanCoordinator.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTitleMetadataHelper.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerEarnedTrophyPersister.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanCatalogSideEffects.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTitleCatalogSynchronizer.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTrophyProgressSynchronizer.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanStaleGameDeletionService.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanCompletionService.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTrophyTitleRefresher.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTrophyTitleLoop.php';

final class PlayerScanTrophyTitleLoopTest extends TestCase
{
    private PDO $database;
    private PlayerScanTrophyTitleLoop $trophyTitleLoop;
    /** @var list<int> */
    private array $sleepCalls = [];

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE log (message TEXT NOT NULL)');
        $this->database->exec('CREATE TABLE player_queue (online_id TEXT PRIMARY KEY)');
        $this->database->exec('CREATE TABLE player (account_id INTEGER PRIMARY KEY, online_id TEXT NOT NULL, last_updated_date TEXT)');
        $this->database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY, scanning TEXT, scan_progress TEXT)');
        $this->database->exec("INSERT INTO setting (id, scanning, scan_progress) VALUES (1, 'ExampleUser', NULL)");
        $this->database->exec('CREATE TABLE trophy_title_player (
            account_id TEXT NOT NULL,
            np_communication_id TEXT NOT NULL,
            last_updated_date TEXT,
            PRIMARY KEY (account_id, np_communication_id)
        )');

        $this->sleepCalls = [];
        $logger = new Psn100Logger($this->database);
        $titleMetadataHelper = new PlayerScanTitleMetadataHelper();
        $workerScanCoordinator = new WorkerScanCoordinator($this->database);
        $trophyCalculator = new TrophyCalculator($this->database);
        $historyRecorder = new TrophyHistoryRecorder($this->database, $logger);
        $automaticTrophyTitleMergeService = new AutomaticTrophyTitleMergeService(
            $this->database,
            new TrophyMergeService($this->database)
        );
        $earnedTrophyPersister = new PlayerEarnedTrophyPersister($this->database, $titleMetadataHelper);

        $this->trophyTitleLoop = new PlayerScanTrophyTitleLoop(
            $this->database,
            $logger,
            $workerScanCoordinator,
            $titleMetadataHelper,
            new PlayerScanTitleCatalogSynchronizer(
                $this->database,
                $logger,
                catalogSideEffects: new PlayerScanCatalogSideEffects(
                    $this->database,
                    $historyRecorder,
                    $automaticTrophyTitleMergeService,
                ),
            ),
            new PlayerScanTrophyProgressSynchronizer(
                $this->database,
                $trophyCalculator,
                $logger,
                $earnedTrophyPersister,
                $automaticTrophyTitleMergeService,
            ),
            new PlayerScanStaleGameDeletionService($this->database),
            new PlayerScanCompletionService($this->database),
            new PlayerScanTrophyTitleRefresher($logger, $titleMetadataHelper, $workerScanCoordinator),
            function (int $seconds): void {
                $this->sleepCalls[] = $seconds;
            },
        );
    }

    public function testDetermineScanStartIndexRescansWhenInvalidTimestampsDiffer(): void
    {
        $trophyTitles = [
            new PlayerScanTrophyTitleLoopTestTrophyTitle('NPWR12345_00', 'not-a-valid-date'),
        ];

        $gameLastUpdatedDate = [
            'NPWR12345_00' => 'also-not-valid',
        ];

        $result = $this->trophyTitleLoop->determineScanStartIndex($trophyTitles, $gameLastUpdatedDate);

        $this->assertSame(0, $result);
    }

    public function testDetermineScanStartIndexRescansWhenInvalidTimestampsMatch(): void
    {
        $invalidTimestamp = 'not-a-valid-date';
        $trophyTitles = [
            new PlayerScanTrophyTitleLoopTestTrophyTitle('NPWR12345_00', $invalidTimestamp),
        ];

        $gameLastUpdatedDate = [
            'NPWR12345_00' => $invalidTimestamp,
        ];

        $result = $this->trophyTitleLoop->determineScanStartIndex($trophyTitles, $gameLastUpdatedDate);

        $this->assertSame(0, $result);
    }

    public function testHandleInvalidTitleLastUpdatedDateResponseDefersPlayerScan(): void
    {
        $this->database->exec("INSERT INTO player_queue (online_id) VALUES ('ExampleUser')");

        $reflection = new ReflectionMethod(PlayerScanTrophyTitleLoop::class, 'handleInvalidTitleLastUpdatedDateResponse');
        $reflection->setAccessible(true);
        $reflection->invoke(
            $this->trophyTitleLoop,
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

    public function testProcessAccessibleTrophyTitlesRetriesTransientFetchExceptions(): void
    {
        $recheck = 'ExampleUser';
        $missingGameDeletionCheck = ['ExampleUser' => true, 'OtherUser' => true];
        $missingTrophyTitleRetry = ['ExampleUser' => true];
        $trophyTitleCountRetry = ['ExampleUser' => true];
        $invalidTitleDateRetry = ['ExampleUser:NPWR12345_00' => true, 'OtherUser:NPWR99999_00' => true];

        $result = $this->trophyTitleLoop->processAccessibleTrophyTitles(
            new stdClass(),
            new PlayerScanTrophyTitleLoopTestUserThatThrowsOnTrophyTitles(
                new RuntimeException('cURL error 18: transfer closed with outstanding read data remaining')
            ),
            ['online_id' => 'ExampleUser'],
            ['id' => 1],
            'ExampleUser',
            $recheck,
            $missingGameDeletionCheck,
            $missingTrophyTitleRetry,
            $trophyTitleCountRetry,
            $invalidTitleDateRetry,
        );

        $this->assertTrue($result->shouldContinueLoop());
        $this->assertSame([5], $this->sleepCalls);
        $this->assertSame('', $recheck);
        $this->assertSame(['OtherUser' => true], $missingGameDeletionCheck);
        $this->assertSame([], $missingTrophyTitleRetry);
        $this->assertSame([], $trophyTitleCountRetry);
        $this->assertSame(['OtherUser:NPWR99999_00' => true], $invalidTitleDateRetry);

        $scanProgress = $this->database->query('SELECT scan_progress FROM setting WHERE id = 1')->fetchColumn();
        $this->assertTrue(is_string($scanProgress));
        $this->assertStringContainsString('fetching game list', $scanProgress);

        $logMessage = $this->database->query('SELECT message FROM log ORDER BY rowid DESC LIMIT 1')->fetchColumn();
        $this->assertStringContainsString('cURL error 18', (string) $logMessage);
        $this->assertStringContainsString('ExampleUser', (string) $logMessage);
    }

    public function testProcessAccessibleTrophyTitlesRetriesTypeErrorsQuickly(): void
    {
        $recheck = 'ExampleUser';
        $missingGameDeletionCheck = ['ExampleUser' => true];
        $missingTrophyTitleRetry = ['ExampleUser' => true];
        $trophyTitleCountRetry = ['ExampleUser' => true];
        $invalidTitleDateRetry = ['ExampleUser:NPWR12345_00' => true];

        $result = $this->trophyTitleLoop->processAccessibleTrophyTitles(
            new stdClass(),
            new PlayerScanTrophyTitleLoopTestUserThatThrowsOnTrophyTitles(
                new TypeError('Unexpected trophyTitles payload')
            ),
            ['online_id' => 'ExampleUser'],
            ['id' => 1],
            'ExampleUser',
            $recheck,
            $missingGameDeletionCheck,
            $missingTrophyTitleRetry,
            $trophyTitleCountRetry,
            $invalidTitleDateRetry,
        );

        $this->assertTrue($result->shouldContinueLoop());
        $this->assertSame([5], $this->sleepCalls);
        $this->assertSame('', $recheck);
        $this->assertSame([], $missingGameDeletionCheck);
        $this->assertSame([], $missingTrophyTitleRetry);
        $this->assertSame([], $trophyTitleCountRetry);
        $this->assertSame([], $invalidTitleDateRetry);
    }
}

final class PlayerScanTrophyTitleLoopTestUserThatThrowsOnTrophyTitles
{
    public function __construct(private readonly Throwable $exception)
    {
    }

    public function accountId(): string
    {
        return '2498580493801829235';
    }

    public function trophySummary(): PlayerScanTrophyTitleLoopTestTrophySummary
    {
        return new PlayerScanTrophyTitleLoopTestTrophySummary();
    }

    public function trophyTitles(): never
    {
        throw $this->exception;
    }
}

final class PlayerScanTrophyTitleLoopTestTrophySummary
{
    public function platinum(): int
    {
        return 0;
    }

    public function gold(): int
    {
        return 0;
    }

    public function silver(): int
    {
        return 0;
    }

    public function bronze(): int
    {
        return 0;
    }
}

final class PlayerScanTrophyTitleLoopTestTrophyTitle
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
