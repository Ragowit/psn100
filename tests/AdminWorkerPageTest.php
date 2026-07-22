<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/AdminRequest.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerAction.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerPage.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerPageResult.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerPageSortLink.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerCredentialField.php';
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
        $this->database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY, refresh_token TEXT, npsso TEXT, scanning TEXT, scan_start TEXT, scan_progress TEXT)');
    }

    public function testHandleReturnsSortedWorkersAndLinks(): void
    {
        $this->database->exec("INSERT INTO setting (id, refresh_token, npsso, scanning, scan_start, scan_progress) VALUES (1, '', 'foo', 'Alpha', '2024-01-01 00:00:00', null)");
        $this->database->exec("INSERT INTO setting (id, refresh_token, npsso, scanning, scan_start, scan_progress) VALUES (2, '', 'bar', 'Bravo', '2024-01-02 00:00:00', null)");

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
        $this->database->exec("INSERT INTO setting (id, refresh_token, npsso, scanning, scan_start, scan_progress) VALUES (5, '', 'old', 'Worker', '2024-01-01 00:00:00', null)");

        $service = new WorkerService($this->database, new FakeCommandExecutor(new CommandExecutionResult(0, '')));
        $page = new WorkerPage($service);

        $request = new AdminRequest('POST', ['action' => WorkerAction::UpdateNpsso->value, 'worker_id' => '5', 'npsso' => 'updated']);
        $result = $page->handle([], $request);

        $this->assertSame('Worker NPSSO updated successfully.', $result->getSuccessMessage());
        $this->assertSame(null, $result->getErrorMessage());

        $workers = $result->getWorkers();
        $this->assertCount(1, $workers);
        $this->assertSame('updated', $workers[0]->getNpsso());
    }

    public function testHandleProcessesRestartWorkerRequests(): void
    {
        $this->database->exec("INSERT INTO setting (id, refresh_token, npsso, scanning, scan_start, scan_progress) VALUES (3, '', 'np', 'Worker', '2024-01-01 00:00:00', null)");

        $service = new WorkerService($this->database, new FakeCommandExecutor(new CommandExecutionResult(0, 'Done')));
        $page = new WorkerPage($service);

        $request = new AdminRequest('POST', ['action' => WorkerAction::RestartWorker->value, 'worker_id' => '3']);
        $result = $page->handle([], $request);

        $this->assertSame('Worker #3 restart signal sent successfully. Done', $result->getSuccessMessage());
        $this->assertSame(null, $result->getErrorMessage());
    }

    public function testHandleProcessesUpdateRefreshTokenRequests(): void
    {
        $this->database->exec("INSERT INTO setting (id, refresh_token, npsso, scanning, scan_start, scan_progress) VALUES (7, 'old-token', 'np', 'Worker', '2024-01-01 00:00:00', null)");

        $service = new WorkerService($this->database, new FakeCommandExecutor(new CommandExecutionResult(0, '')));
        $page = new WorkerPage($service);

        $request = new AdminRequest('POST', ['action' => WorkerAction::UpdateRefreshToken->value, 'worker_id' => '7', 'refresh_token' => 'new-refresh-token-value']);
        $result = $page->handle([], $request);

        $this->assertSame('Worker refresh token updated successfully.', $result->getSuccessMessage());
        $this->assertSame(null, $result->getErrorMessage());
        $this->assertSame('new-refresh-token-value', $result->getWorkers()[0]->getRefreshToken());
    }

    public function testHandleProcessesRestartAllWorkersFailure(): void
    {
        $this->database->exec("INSERT INTO setting (id, refresh_token, npsso, scanning, scan_start, scan_progress) VALUES (1, '', 'np', 'Worker', '2024-01-01 00:00:00', null)");

        $service = new WorkerService($this->database, new FakeCommandExecutor(new CommandExecutionResult(2, 'error')));
        $page = new WorkerPage($service);

        $request = new AdminRequest('POST', ['action' => WorkerAction::RestartAllWorkers->value]);
        $result = $page->handle([], $request);

        $this->assertSame(null, $result->getSuccessMessage());
        $this->assertSame('Unable to restart all workers (exit code 2). error', $result->getErrorMessage());
    }

    public function testWorkersTemplateMasksCredentialsInsteadOfRenderingValues(): void
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/admin/workers.php');

        $this->assertTrue(is_string($source));
        $this->assertTrue(str_contains($source, 'WorkerAction::UpdateNpsso'));
        $this->assertTrue(str_contains($source, 'WorkerAction::RestartWorker'));
        $this->assertTrue(str_contains($source, 'WorkerCredentialMasker::mask'));
        $this->assertTrue(str_contains($source, 'admin-worker-credentials.js'));
        $this->assertFalse(str_contains($source, 'getRefreshToken(), ENT_QUOTES'));
        $this->assertFalse(str_contains($source, 'getNpsso(), ENT_QUOTES'));

        $scriptSource = file_get_contents(__DIR__ . '/../wwwroot/js/admin-worker-credentials.js');
        $this->assertTrue(is_string($scriptSource));
        $this->assertTrue(str_contains($scriptSource, 'worker-credential.php'));
    }

    public function testWorkerServiceFetchWorkerCredentialReturnsStoredValue(): void
    {
        $this->database->exec("INSERT INTO setting (id, refresh_token, npsso, scanning, scan_start, scan_progress) VALUES (4, 'secret-refresh', 'secret-npsso', '', '2024-01-01 00:00:00', null)");

        $service = new WorkerService($this->database, new FakeCommandExecutor(new CommandExecutionResult(0, '')));

        $this->assertSame('secret-refresh', $service->fetchWorkerCredential(4, WorkerCredentialField::RefreshToken));
        $this->assertSame('secret-npsso', $service->fetchWorkerCredential(4, WorkerCredentialField::Npsso));
        $this->assertSame(null, $service->fetchWorkerCredential(99, WorkerCredentialField::Npsso));
    }

    private PDO $database;
}

final class FakeCommandExecutor implements CommandExecutorInterface
{
    public function __construct(private readonly CommandExecutionResult $result)
    {
    }

    #[\Override]
    public function run(array $command): CommandExecutionResult
    {
        return $this->result;
    }
}
