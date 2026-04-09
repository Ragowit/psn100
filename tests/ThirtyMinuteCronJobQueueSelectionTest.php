<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyHistoryRecorder.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/ThirtyMinuteCronJob.php';

final class ThirtyMinuteCronJobQueueSelectionTest extends TestCase
{
    private PDO $database;
    private ThirtyMinuteCronJob $cronJob;
    private ReflectionMethod $reservePlayerMethod;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY, npsso TEXT, scanning TEXT, scan_progress TEXT)');
        $this->database->exec('CREATE TABLE log (message TEXT NOT NULL)');
        $this->database->exec("INSERT INTO setting (id, npsso, scanning, scan_progress) VALUES (1, 'token', 'queued-user', NULL)");

        $logger = new Psn100Logger($this->database);
        $this->cronJob = new ThirtyMinuteCronJob(
            $this->database,
            new TrophyCalculator($this->database),
            $logger,
            new TrophyHistoryRecorder($this->database, $logger),
            1
        );

        $this->reservePlayerMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'reservePlayerForScanning');
        $this->reservePlayerMethod->setAccessible(true);
    }

    public function testEmptyQueueSetsWaitingStateWithoutArrayOffsetAccess(): void
    {
        $reservedPlayer = $this->reservePlayerMethod->invoke($this->cronJob, 1, false);

        $this->assertSame(null, $reservedPlayer);

        $settingQuery = $this->database->query('SELECT scanning, scan_progress FROM setting WHERE id = 1');
        $setting = $settingQuery !== false ? $settingQuery->fetch(PDO::FETCH_ASSOC) : false;
        $this->assertTrue(is_array($setting));
        $this->assertSame('1', (string) $setting['scanning']);
        $this->assertTrue(is_string($setting['scan_progress']));

        $scanProgress = json_decode((string) $setting['scan_progress'], true);
        $this->assertTrue(is_array($scanProgress));
        $this->assertSame('No player to scan; retrying soon.', (string) ($scanProgress['title'] ?? ''));
    }

    public function testSelectedPlayerUpdatesWorkerScanningValue(): void
    {
        $reservedPlayer = $this->reservePlayerMethod->invoke(
            $this->cronJob,
            1,
            [
                'online_id' => 'player-123',
                'account_id' => 55,
            ]
        );

        $this->assertTrue(is_array($reservedPlayer));
        $this->assertSame('player-123', (string) ($reservedPlayer['online_id'] ?? ''));

        $settingQuery = $this->database->query('SELECT scanning, scan_progress FROM setting WHERE id = 1');
        $setting = $settingQuery !== false ? $settingQuery->fetch(PDO::FETCH_ASSOC) : false;
        $this->assertTrue(is_array($setting));
        $this->assertSame('player-123', (string) $setting['scanning']);
        $this->assertSame(null, $setting['scan_progress']);
    }
}
