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

    public function testDoesNotLockUntilFifthFailure(): void
    {
        $ipAddress = '192.0.2.22';
        $statement = $this->pdo->prepare(
            <<<'SQL'
            INSERT INTO admin_login_throttle (ip_address, failure_count, locked_until, last_attempt_at)
            VALUES (:ip_address, :failure_count, NULL, :last_attempt_at)
            SQL
        );
        $statement->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $statement->bindValue(':failure_count', AdminLoginThrottleService::MAX_FAILURES - 2, PDO::PARAM_INT);
        $statement->bindValue(':last_attempt_at', '2026-01-01 00:00:00', PDO::PARAM_STR);
        $statement->execute();

        $this->service->recordFailure($ipAddress);
        $this->assertFalse($this->service->isLocked($ipAddress));

        $this->service->recordFailure($ipAddress);
        $this->assertTrue($this->service->isLocked($ipAddress));
    }

    public function testResetsFailureCountAfterLockoutExpires(): void
    {
        $ipAddress = '192.0.2.23';

        for ($index = 0; $index < AdminLoginThrottleService::MAX_FAILURES; $index++) {
            $this->service->recordFailure($ipAddress);
        }

        $this->assertTrue($this->service->isLocked($ipAddress));

        $statement = $this->pdo->prepare(
            'UPDATE admin_login_throttle SET locked_until = :locked_until WHERE ip_address = :ip_address'
        );
        $statement->bindValue(':locked_until', '2020-01-01 00:00:00', PDO::PARAM_STR);
        $statement->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $statement->execute();

        $this->assertFalse($this->service->isLocked($ipAddress));

        $this->service->recordFailure($ipAddress);
        $this->assertFalse($this->service->isLocked($ipAddress));

        for ($index = 0; $index < AdminLoginThrottleService::MAX_FAILURES - 2; $index++) {
            $this->service->recordFailure($ipAddress);
            $this->assertFalse($this->service->isLocked($ipAddress));
        }

        $this->service->recordFailure($ipAddress);
        $this->assertTrue($this->service->isLocked($ipAddress));
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

    public function testMysqlFailureUpsertEvaluatesLockoutBeforeIncrementingFailureCount(): void
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/classes/Admin/AdminLoginThrottleService.php');

        $this->assertTrue(is_string($source));
        $this->assertStringContainsString('locked_until = IF(', $source);
        $this->assertStringContainsString('failure_count = admin_login_throttle.failure_count + 1', $source);

        $lockoutPosition = strpos($source, 'locked_until = IF(');
        $incrementPosition = strpos($source, 'failure_count = admin_login_throttle.failure_count + 1');
        $this->assertTrue(
            is_int($lockoutPosition) && is_int($incrementPosition) && $lockoutPosition < $incrementPosition,
            'MySQL evaluates ON DUPLICATE KEY UPDATE assignments left-to-right; lockout must be decided before failure_count is incremented.'
        );
    }
}
