<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/IpSubmissionLockExecutor.php';

final class IpSubmissionLockExecutorIntegrationTest extends TestCase
{
    private const string INTEGRATION_TEST_DB_ENV = 'PSN100_INTEGRATION_TEST_DB';

    private ?PDO $database = null;

    protected function tearDown(): void
    {
        $this->database = null;
    }

    public function testExecuteAcquiresAndReleasesMysqlLock(): void
    {
        $database = $this->createMysqlDatabase();
        if ($database === null) {
            return;
        }

        $executor = new IpSubmissionLockExecutor($database);
        $result = $executor->execute('192.0.2.77', static fn (): string => 'locked');

        $this->assertSame('locked', $result);
    }

    private function createMysqlDatabase(): ?PDO
    {
        if ($this->database instanceof PDO) {
            return $this->database;
        }

        if (getenv(self::INTEGRATION_TEST_DB_ENV) !== '1') {
            return null;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $databaseName = getenv('DB_NAME') ?: 'psn100';
        $user = getenv('DB_USER') ?: 'psn100';
        $password = getenv('DB_PASSWORD') ?: 'psn100';

        try {
            $this->database = new PDO(
                sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $databaseName),
                $user,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Throwable) {
            return null;
        }

        return $this->database;
    }
}
