<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/WorkerScanCoordinator.php';

final class WorkerScanCoordinatorTest extends TestCase
{
    private PDO $database;
    private WorkerScanCoordinator $coordinator;
    /** @var list<int> */
    private array $sleptSeconds = [];

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY, npsso TEXT, scanning TEXT, scan_progress TEXT)');
        $this->database->exec('CREATE TABLE player_queue (online_id TEXT PRIMARY KEY)');
        $this->database->exec('CREATE TABLE player (account_id INTEGER PRIMARY KEY, online_id TEXT NOT NULL, last_updated_date TEXT)');
        $this->database->exec("INSERT INTO setting (id, npsso, scanning, scan_progress) VALUES (1, 'token', 'queued-user', NULL)");

        $this->sleptSeconds = [];
        $this->coordinator = new WorkerScanCoordinator(
            $this->database,
            function (int $seconds): void {
                $this->sleptSeconds[] = $seconds;
            },
        );
    }

    public function testSetWaitingScanProgressStoresTitleJson(): void
    {
        $this->coordinator->setWaitingScanProgress(1, 'Waiting for queue…');

        $setting = $this->fetchSetting(1);
        $scanProgress = json_decode((string) $setting['scan_progress'], true);

        $this->assertTrue(is_array($scanProgress));
        $this->assertSame('Waiting for queue…', (string) ($scanProgress['title'] ?? ''));
    }

    public function testSetWorkerScanProgressClearsProgressWhenNull(): void
    {
        $this->coordinator->setWaitingScanProgress(1, 'Busy');

        $this->coordinator->setWorkerScanProgress(1, null);

        $setting = $this->fetchSetting(1);
        $this->assertSame(null, $setting['scan_progress']);
    }

    public function testSetWorkerScanProgressStoresDetailedProgress(): void
    {
        $this->coordinator->setWorkerScanProgress(1, [
            'current' => 2,
            'total' => 10,
            'title' => 'Example Game',
            'npCommunicationId' => 'NPWR00001_00',
        ]);

        $setting = $this->fetchSetting(1);
        $scanProgress = json_decode((string) $setting['scan_progress'], true);

        $this->assertTrue(is_array($scanProgress));
        $this->assertSame(2, (int) ($scanProgress['current'] ?? 0));
        $this->assertSame(10, (int) ($scanProgress['total'] ?? 0));
        $this->assertSame('Example Game', (string) ($scanProgress['title'] ?? ''));
        $this->assertSame('NPWR00001_00', (string) ($scanProgress['npCommunicationId'] ?? ''));
    }

    public function testReleaseWorkerFromCurrentScanResetsScanningToWorkerId(): void
    {
        $this->coordinator->setWaitingScanProgress(1, 'Busy');

        $this->coordinator->releaseWorkerFromCurrentScan(1);

        $setting = $this->fetchSetting(1);
        $this->assertSame('1', (string) $setting['scanning']);
        $this->assertSame(null, $setting['scan_progress']);
    }

    public function testEmptyQueueSetsWaitingStateWithoutArrayOffsetAccess(): void
    {
        $reservedPlayer = $this->coordinator->reservePlayerForScanning(1, false);

        $this->assertSame(null, $reservedPlayer);
        $this->assertSame([5], $this->sleptSeconds);

        $setting = $this->fetchSetting(1);
        $scanProgress = json_decode((string) $setting['scan_progress'], true);

        $this->assertSame('1', (string) $setting['scanning']);
        $this->assertTrue(is_array($scanProgress));
        $this->assertSame('No player to scan; retrying soon.', (string) ($scanProgress['title'] ?? ''));
    }

    public function testSelectedPlayerUpdatesWorkerScanningValue(): void
    {
        $reservedPlayer = $this->coordinator->reservePlayerForScanning(
            1,
            [
                'online_id' => 'player-123',
                'account_id' => 55,
            ]
        );

        $this->assertTrue(is_array($reservedPlayer));
        $this->assertSame('player-123', (string) ($reservedPlayer['online_id'] ?? ''));
        $this->assertSame([], $this->sleptSeconds);

        $setting = $this->fetchSetting(1);
        $this->assertSame('player-123', (string) $setting['scanning']);
        $this->assertSame(null, $setting['scan_progress']);
    }

    public function testDeferPlayerScanAfterFailureRemovesQueueEntryAndReleasesWorker(): void
    {
        $this->database->exec("INSERT INTO player_queue (online_id) VALUES ('player-123')");
        $this->coordinator->setWaitingScanProgress(1, 'Scanning player-123');

        $this->coordinator->deferPlayerScanAfterFailure(
            [
                'online_id' => 'player-123',
            ],
            1
        );

        $queueCount = $this->database->query("SELECT COUNT(*) FROM player_queue WHERE online_id = 'player-123'")->fetchColumn();
        $setting = $this->fetchSetting(1);

        $this->assertSame(0, (int) $queueCount);
        $this->assertSame('1', (string) $setting['scanning']);
        $this->assertSame(null, $setting['scan_progress']);
    }

    /**
     * @return array{scanning: mixed, scan_progress: mixed}
     */
    private function fetchSetting(int $workerId): array
    {
        $settingQuery = $this->database->query(sprintf('SELECT scanning, scan_progress FROM setting WHERE id = %d', $workerId));
        $setting = $settingQuery !== false ? $settingQuery->fetch(PDO::FETCH_ASSOC) : false;
        $this->assertTrue(is_array($setting));

        return $setting;
    }
}
