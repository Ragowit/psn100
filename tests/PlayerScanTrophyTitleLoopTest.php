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

    public function testProcessAccessibleTrophyTitlesRetriesTrophySummaryTypeErrorAtStart(): void
    {
        $recheck = 'ExampleUser';
        $missingGameDeletionCheck = ['ExampleUser' => true];
        $missingTrophyTitleRetry = ['ExampleUser' => true];
        $trophyTitleCountRetry = ['ExampleUser' => true];
        $invalidTitleDateRetry = ['ExampleUser:NPWR12345_00' => true];

        $result = $this->trophyTitleLoop->processAccessibleTrophyTitles(
            new stdClass(),
            new PlayerScanTrophyTitleLoopTestUserThatThrowsOnTrophySummary(
                new TypeError(
                    'GuzzleHttp\Middleware::{closure}(): Argument #1 ($response) must be of type'
                    . ' Psr\Http\Message\ResponseInterface, null given'
                )
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

        $scanProgress = $this->database->query('SELECT scan_progress FROM setting WHERE id = 1')->fetchColumn();
        $this->assertTrue(is_string($scanProgress));
        $this->assertStringContainsString('trophy summary', $scanProgress);

        $logMessage = $this->database->query('SELECT message FROM log ORDER BY rowid DESC LIMIT 1')->fetchColumn();
        $this->assertStringContainsString('ResponseInterface', (string) $logMessage);
        $this->assertStringContainsString('ExampleUser', (string) $logMessage);
    }

    public function testProcessAccessibleTrophyTitlesRetriesTrophySummaryTypeErrorAtEnd(): void
    {
        $this->database->exec(
            "INSERT INTO trophy_title_player (account_id, np_communication_id, last_updated_date)
             VALUES ('2498580493801829235', 'NPWR12345_00', '2024-01-01T00:00:00Z')"
        );

        $recheck = 'ExampleUser';
        $missingGameDeletionCheck = [];
        $missingTrophyTitleRetry = [];
        $trophyTitleCountRetry = [];
        $invalidTitleDateRetry = [];

        $result = $this->trophyTitleLoop->processAccessibleTrophyTitles(
            new stdClass(),
            new PlayerScanTrophyTitleLoopTestUserThatThrowsOnEndTrophySummary(
                new TypeError(
                    'GuzzleHttp\Middleware::{closure}(): Argument #1 ($response) must be of type'
                    . ' Psr\Http\Message\ResponseInterface, null given'
                )
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

        $logMessage = $this->database->query('SELECT message FROM log ORDER BY rowid DESC LIMIT 1')->fetchColumn();
        $this->assertStringContainsString('Failed to fetch trophy summary', (string) $logMessage);
        $this->assertStringContainsString('null given', (string) $logMessage);
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

final class PlayerScanTrophyTitleLoopTestUserThatThrowsOnTrophySummary
{
    public function __construct(private readonly Throwable $exception)
    {
    }

    public function accountId(): string
    {
        return '2498580493801829235';
    }

    public function trophySummary(): never
    {
        throw $this->exception;
    }

    public function trophyTitles(): never
    {
        throw new RuntimeException('trophyTitles should not be called when trophySummary fails first');
    }
}

final class PlayerScanTrophyTitleLoopTestUserThatThrowsOnEndTrophySummary
{
    private int $trophySummaryCalls = 0;

    public function __construct(private readonly Throwable $exception)
    {
    }

    public function accountId(): string
    {
        return '2498580493801829235';
    }

    public function trophySummary(): PlayerScanTrophyTitleLoopTestTrophySummary
    {
        $this->trophySummaryCalls++;

        if ($this->trophySummaryCalls > 1) {
            throw $this->exception;
        }

        return new PlayerScanTrophyTitleLoopTestTrophySummary();
    }

    public function trophyTitles(): PlayerScanTrophyTitleLoopTestTrophyTitleCollection
    {
        return new PlayerScanTrophyTitleLoopTestTrophyTitleCollection([
            new PlayerScanTrophyTitleLoopTestTrophyTitle(
                'NPWR12345_00',
                '2024-01-01T00:00:00Z',
                'Example Game'
            ),
        ]);
    }
}

final class PlayerScanTrophyTitleLoopTestTrophyTitleCollection
{
    /**
     * @param list<object> $titles
     */
    public function __construct(private readonly array $titles)
    {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->titles);
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
        private readonly string $name = '',
        private readonly string $iconUrl = 'https://example.test/icon.png',
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

    public function iconUrl(): string
    {
        return $this->iconUrl;
    }
}
