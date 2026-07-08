<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/AdminRequest.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerCredentialRevealHandler.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerCredentialRevealResult.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerService.php';

final class WorkerCredentialRevealHandlerTest extends TestCase
{
    private PDO $database;

    private WorkerCredentialRevealHandler $handler;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(
            'CREATE TABLE setting (
                id INTEGER PRIMARY KEY,
                refresh_token TEXT NOT NULL,
                npsso TEXT NOT NULL,
                scanning TEXT NOT NULL DEFAULT \'\',
                scan_start TEXT NOT NULL DEFAULT \'1970-01-01 00:00:00\',
                scan_progress TEXT
            )'
        );
        $this->database->exec(
            "INSERT INTO setting (id, refresh_token, npsso) VALUES (1, 'refresh-secret-token', 'npsso-secret-value')"
        );

        $this->handler = new WorkerCredentialRevealHandler(new WorkerService($this->database));
    }

    public function testHandleRevealsRefreshTokenForExistingWorker(): void
    {
        $result = $this->handler->handle($this->createPostRequest([
            'worker_id' => '1',
            'credential' => 'refresh_token',
        ]));

        $this->assertTrue($result->isSuccess());
        $this->assertSame('refresh-secret-token', $result->getCredential());
        $this->assertSame([
            'status' => 'ok',
            'credential' => 'refresh-secret-token',
        ], $result->toPayload());
    }

    public function testHandleRevealsNpssoForExistingWorker(): void
    {
        $result = $this->handler->handle($this->createPostRequest([
            'worker_id' => '1',
            'credential' => 'npsso',
        ]));

        $this->assertTrue($result->isSuccess());
        $this->assertSame('npsso-secret-value', $result->getCredential());
    }

    public function testHandleRejectsInvalidCredentialField(): void
    {
        $result = $this->handler->handle($this->createPostRequest([
            'worker_id' => '1',
            'credential' => 'password',
        ]));

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Invalid worker credential request.', $result->getErrorMessage());
    }

    public function testHandleRejectsMissingWorker(): void
    {
        $result = $this->handler->handle($this->createPostRequest([
            'worker_id' => '99',
            'credential' => 'npsso',
        ]));

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Worker not found.', $result->getErrorMessage());
    }

    /**
     * @param array<string, string> $postData
     */
    private function createPostRequest(array $postData): AdminRequest
    {
        return AdminRequest::fromGlobals(
            ['REQUEST_METHOD' => 'POST'],
            $postData,
        );
    }
}
