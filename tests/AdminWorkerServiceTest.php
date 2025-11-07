<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerService.php';

final class AdminWorkerServiceTest extends TestCase
{
    public function testFetchWorkersOrdersByScanStart(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT, refresh_token TEXT, npsso TEXT, scanning TEXT, scan_start TEXT, scan_progress TEXT)');

        $database->exec("INSERT INTO setting (refresh_token, npsso, scanning, scan_start) VALUES ('token-2', 'npsso-2', 'player-two', '2024-01-02 10:00:00')");
        $database->exec("INSERT INTO setting (refresh_token, npsso, scanning, scan_start) VALUES ('token-1', 'npsso-1', 'player-one', '2024-01-01 09:00:00')");
        $database->exec("INSERT INTO setting (refresh_token, npsso, scanning, scan_start) VALUES ('token-3', 'npsso-3', '', '2024-01-03 08:00:00')");

        $service = new WorkerService($database);
        $workers = $service->fetchWorkers();

        $this->assertCount(3, $workers);
        $this->assertSame('player-one', $workers[0]->getScanning());
        $this->assertSame('2024-01-01 09:00:00', $workers[0]->getScanStart()->format('Y-m-d H:i:s'));
    }

    public function testUpdateWorkerNpssoReturnsTrueWhenRowUpdated(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT, refresh_token TEXT, npsso TEXT, scanning TEXT, scan_start TEXT, scan_progress TEXT)');
        $database->exec("INSERT INTO setting (refresh_token, npsso, scanning, scan_start) VALUES ('token-1', 'old-npsso', 'player-one', '2024-01-01 09:00:00')");

        $service = new WorkerService($database);
        $this->assertTrue($service->updateWorkerNpsso(1, 'new-npsso'));

        $statement = $database->query('SELECT npsso FROM setting WHERE id = 1');
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('new-npsso', $row['npsso']);
    }

    public function testUpdateWorkerNpssoReturnsFalseWhenRowMissing(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT, refresh_token TEXT, npsso TEXT, scanning TEXT, scan_start TEXT, scan_progress TEXT)');

        $service = new WorkerService($database);
        $this->assertFalse($service->updateWorkerNpsso(42, 'does-not-exist'));
    }
}
