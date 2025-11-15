<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/AdminRequest.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerPage.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerPageResult.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerPageSortLink.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/Worker.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/CommandExecutionResult.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/CommandExecutorInterface.php';

final class AdminWorkerPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY, npsso TEXT, scanning TEXT, scan_start TEXT, scan_progress TEXT)');
    }

    public function testHandleReturnsSortedWorkersAndLinks(): void
    {
        $this->database->exec("INSERT INTO setting (id, npsso, scanning, scan_start, scan_progress) VALUES (1, 'foo', 'Alpha', '2024-01-01 00:00:00', null)");
        $this->database->exec("INSERT INTO setting (id, npsso, scanning, scan_start, scan_progress) VALUES (2, 'bar', 'Bravo', '2024-01-02 00:00:00', null)");

        $service = new WorkerService($this->database, new FakeCommandExecutor(new CommandExecutionResult(0, '')));
        $page = new WorkerPage($service);

        $request = new AdminRequest('GET', []);
        $result = $page->handle(['sort' => 'id', 'direction' => 'DESC'], $request);

        $workers = $result->getWorkers();
        $this->assertCount(2, $workers);
        $this->assertSame(2, $workers[0]->getId());
        $this->assertSame('id', $result->getSortField());
        $this->assertSame('desc', $result->getSortDirection());

        $idSortLink = $result->getSortLink('id');
        $this->assertTrue($idSortLink instanceof WorkerPageSortLink);
        $this->assertSame('?sort=id&direction=asc', $idSortLink->getUrl());
        $this->assertSame(' ▼', $idSortLink->getIndicator());

        $scanSortLink = $result->getSortLink('scan_start');
        $this->assertTrue($scanSortLink instanceof WorkerPageSortLink);
        $this->assertSame('?sort=scan_start&direction=asc', $scanSortLink->getUrl());
        $this->assertSame('', $scanSortLink->getIndicator());
    }

    public function testHandleProcessesUpdateNpssoRequests(): void
    {
        $this->database->exec("INSERT INTO setting (id, npsso, scanning, scan_start, scan_progress) VALUES (5, 'old', 'Worker', '2024-01-01 00:00:00', null)");

        $service = new WorkerService($this->database, new FakeCommandExecutor(new CommandExecutionResult(0, '')));
        $page = new WorkerPage($service);

        $request = new AdminRequest('POST', ['action' => 'update_npsso', 'worker_id' => '5', 'npsso' => 'updated']);
        $result = $page->handle([], $request);

        $this->assertSame('Worker NPSSO updated successfully.', $result->getSuccessMessage());
        $this->assertSame(null, $result->getErrorMessage());

        $workers = $result->getWorkers();
        $this->assertCount(1, $workers);
        $this->assertSame('updated', $workers[0]->getNpsso());
    }

    public function testHandleProcessesRestartWorkerRequests(): void
    {
        $this->database->exec("INSERT INTO setting (id, npsso, scanning, scan_start, scan_progress) VALUES (3, 'np', 'Worker', '2024-01-01 00:00:00', null)");

        $service = new WorkerService($this->database, new FakeCommandExecutor(new CommandExecutionResult(0, 'Done')));
        $page = new WorkerPage($service);

        $request = new AdminRequest('POST', ['action' => 'restart_worker', 'worker_id' => '3']);
        $result = $page->handle([], $request);

        $this->assertSame('Worker #3 restart signal sent successfully. Done', $result->getSuccessMessage());
        $this->assertSame(null, $result->getErrorMessage());
    }

    public function testHandleProcessesRestartAllWorkersFailure(): void
    {
        $this->database->exec("INSERT INTO setting (id, npsso, scanning, scan_start, scan_progress) VALUES (1, 'np', 'Worker', '2024-01-01 00:00:00', null)");

        $service = new WorkerService($this->database, new FakeCommandExecutor(new CommandExecutionResult(2, 'error')));
        $page = new WorkerPage($service);

        $request = new AdminRequest('POST', ['action' => 'restart_all_workers']);
        $result = $page->handle([], $request);

        $this->assertSame(null, $result->getSuccessMessage());
        $this->assertSame('Unable to restart all workers (exit code 2). error', $result->getErrorMessage());
    }

    private PDO $database;
}

final class FakeCommandExecutor implements CommandExecutorInterface
{
    private CommandExecutionResult $result;

    public function __construct(CommandExecutionResult $result)
    {
        $this->result = $result;
    }

    public function run(array $command): CommandExecutionResult
    {
        return $this->result;
    }
}
