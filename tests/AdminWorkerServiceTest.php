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
        $database->exec(<<<'SQL'
INSERT INTO setting (refresh_token, npsso, scanning, scan_start, scan_progress)
VALUES ('token-1', 'npsso-1', 'player-one', '2024-01-01 09:00:00', '{"current": 5, "total": 10, "title": "Example"}')
SQL
        );
        $database->exec("INSERT INTO setting (refresh_token, npsso, scanning, scan_start) VALUES ('token-3', 'npsso-3', '', '2024-01-03 08:00:00')");

        $service = new WorkerService($database);
        $workers = $service->fetchWorkers();

        $this->assertCount(3, $workers);
        $this->assertSame('player-one', $workers[0]->getScanning());
        $this->assertSame('2024-01-01 09:00:00', $workers[0]->getScanStart()->format('Y-m-d H:i:s'));
        $this->assertSame(
            [
                'current' => 5,
                'total' => 10,
                'title' => 'Example',
            ],
            $workers[0]->getScanProgress()
        );
    }

    public function testFetchWorkersCanOrderByIdDescending(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT, refresh_token TEXT, npsso TEXT, scanning TEXT, scan_start TEXT, scan_progress TEXT)');

        $database->exec("INSERT INTO setting (id, refresh_token, npsso, scanning, scan_start) VALUES (1, 'token-1', 'npsso-1', '', '2024-01-01 09:00:00')");
        $database->exec("INSERT INTO setting (id, refresh_token, npsso, scanning, scan_start) VALUES (2, 'token-2', 'npsso-2', '', '2024-01-02 09:00:00')");

        $service = new WorkerService($database);
        $workers = $service->fetchWorkers('id', 'DESC');

        $this->assertCount(2, $workers);
        $this->assertSame(2, $workers[0]->getId());
        $this->assertSame(1, $workers[1]->getId());
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

    public function testFetchWorkersHandlesInvalidScanProgress(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT, refresh_token TEXT, npsso TEXT, scanning TEXT, scan_start TEXT, scan_progress TEXT)');

        $database->exec("INSERT INTO setting (refresh_token, npsso, scanning, scan_start, scan_progress) VALUES ('token-1', 'npsso-1', 'player-one', '2024-01-01 09:00:00', 'not-json')");

        $service = new WorkerService($database);
        $workers = $service->fetchWorkers();

        $this->assertCount(1, $workers);
        $this->assertSame(null, $workers[0]->getScanProgress());
    }

    public function testRestartAllWorkersIssuesPkillCommand(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $result = new CommandExecutionResult(0, '');
        $executor = new class ($result) implements CommandExecutorInterface {
            public array $commands = [];

            private CommandExecutionResult $result;

            public function __construct(CommandExecutionResult $result)
            {
                $this->result = $result;
            }

            public function run(array $command): CommandExecutionResult
            {
                $this->commands[] = $command;

                return $this->result;
            }
        };

        $service = new WorkerService($database, $executor);
        $executionResult = $service->restartAllWorkers();

        $this->assertTrue($executionResult->isSuccessful());
        $this->assertSame([
            ['pkill', '-u', 'psn100', '-f', '30th_minute.php'],
        ], $executor->commands);
    }

    public function testRestartWorkerUsesWorkerSpecificPattern(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $result = new CommandExecutionResult(1, 'No process found');
        $executor = new class ($result) implements CommandExecutorInterface {
            public array $commands = [];

            private CommandExecutionResult $result;

            public function __construct(CommandExecutionResult $result)
            {
                $this->result = $result;
            }

            public function run(array $command): CommandExecutionResult
            {
                $this->commands[] = $command;

                return $this->result;
            }
        };

        $service = new WorkerService($database, $executor);
        $executionResult = $service->restartWorker(3);

        $this->assertFalse($executionResult->isSuccessful());
        $this->assertSame([
            ['pkill', '-u', 'psn100', '-f', 'worker=3'],
        ], $executor->commands);
        $this->assertSame('No process found', $executionResult->getOutput());
        $this->assertSame(1, $executionResult->getExitCode());
    }
}
