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
                historyRecorder: $historyRecorder,
                automaticTrophyTitleMergeService: $automaticTrophyTitleMergeService,
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
