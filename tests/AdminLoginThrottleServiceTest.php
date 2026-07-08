<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/AdminLoginThrottleService.php';

final class AdminLoginThrottleServiceTest extends TestCase
{
    private PDO $pdo;

    private AdminLoginThrottleService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            <<<'SQL'
            CREATE TABLE admin_login_throttle (
                ip_address TEXT PRIMARY KEY,
                failure_count INTEGER NOT NULL,
                locked_until TEXT NULL,
                last_attempt_at TEXT NOT NULL
            )
            SQL
        );

        $this->service = new AdminLoginThrottleService($this->pdo);
    }

    public function testLocksOutAfterMaxFailures(): void
    {
        $ipAddress = '192.0.2.20';

        for ($index = 0; $index < AdminLoginThrottleService::MAX_FAILURES; $index++) {
            $this->service->recordFailure($ipAddress);
        }

        $this->assertTrue($this->service->isLocked($ipAddress));
        $this->assertTrue($this->service->getLockoutRemainingSeconds($ipAddress) > 0);
    }

    public function testRecordSuccessClearsThrottleState(): void
    {
        $ipAddress = '192.0.2.21';

        for ($index = 0; $index < AdminLoginThrottleService::MAX_FAILURES; $index++) {
            $this->service->recordFailure($ipAddress);
        }

        $this->service->recordSuccess($ipAddress);

        $this->assertFalse($this->service->isLocked($ipAddress));
        $this->assertSame(0, $this->service->getLockoutRemainingSeconds($ipAddress));
    }
}
