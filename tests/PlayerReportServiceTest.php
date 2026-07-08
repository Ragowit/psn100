<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerReportService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerReportResult.php';
require_once __DIR__ . '/../wwwroot/classes/IpSubmissionLockExecutor.php';
require_once __DIR__ . '/../wwwroot/classes/IpSubmissionLockUnavailableException.php';

final class PlayerReportServiceTest extends TestCase
{
    private PDO $pdo;

    private PlayerReportService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            <<<'SQL'
            CREATE TABLE player_report (
                report_id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER NOT NULL,
                ip_address TEXT NOT NULL,
                explanation TEXT NOT NULL,
                UNIQUE (account_id, ip_address)
            )
            SQL
        );

        $this->service = new PlayerReportService($this->pdo);
    }

    public function testSubmitReportInsertsNewRecordWhenNoConflicts(): void
    {
        $result = $this->service->submitReport(123, '198.51.100.10', 'Cheating behavior');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Player reported successfully.', $result->getMessage());

        $statement = $this->pdo->query('SELECT account_id, ip_address, explanation FROM player_report');
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows);
        $this->assertSame([
            'account_id' => 123,
            'ip_address' => '198.51.100.10',
            'explanation' => 'Cheating behavior',
        ], $rows[0]);
    }

    public function testSubmitReportReturnsErrorWhenDuplicateExists(): void
    {
        $this->pdo->prepare(
            'INSERT INTO player_report (account_id, ip_address, explanation) VALUES (:account_id, :ip_address, :explanation)'
        )->execute([
            ':account_id' => 456,
            ':ip_address' => '203.0.113.20',
            ':explanation' => 'Already reported',
        ]);

        $result = $this->service->submitReport(456, '203.0.113.20', 'New explanation');

        $this->assertFalse($result->isSuccess());
        $this->assertSame("You've already reported this player.", $result->getMessage());

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM player_report')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testSubmitReportReturnsErrorWhenIpLimitReached(): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO player_report (account_id, ip_address, explanation) VALUES (:account_id, :ip_address, :explanation)'
        );

        for ($i = 0; $i < 10; $i++) {
            $insert->execute([
                ':account_id' => 1000 + $i,
                ':ip_address' => '192.0.2.55',
                ':explanation' => 'Pending report #' . $i,
            ]);
        }

        $result = $this->service->submitReport(9999, '192.0.2.55', 'Another report');

        $this->assertFalse($result->isSuccess());
        $this->assertSame(
            'You already have 10 reports waiting to be processed. Please try again later.',
            $result->getMessage()
        );

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM player_report')->fetchColumn();
        $this->assertSame(10, $count);
    }

    public function testSubmitReportReturnsErrorWhenExplanationIsTooLong(): void
    {
        $result = $this->service->submitReport(123, '198.51.100.10', str_repeat('a', 257));

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Explanation must be 256 characters or fewer.', $result->getMessage());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM player_report')->fetchColumn());
    }

    public function testSubmitReportAcceptsExplanationAtMaximumLength(): void
    {
        $explanation = str_repeat('a', 256);

        $result = $this->service->submitReport(123, '198.51.100.10', $explanation);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM player_report')->fetchColumn());
    }

    public function testSubmitReportReturnsDuplicateErrorWhenUniqueConstraintIsViolated(): void
    {
        $service = new PlayerReportService($this->pdo);
        $insertReport = new ReflectionMethod($service, 'insertReport');
        $insertReport->setAccessible(true);
        $isDuplicate = new ReflectionMethod($service, 'isDuplicateReportException');
        $isDuplicate->setAccessible(true);

        $insertReport->invoke($service, 789, '198.51.100.99', 'Existing report');

        try {
            $insertReport->invoke($service, 789, '198.51.100.99', 'Race condition report');
            $this->fail('Expected PDOException was not thrown.');
        } catch (PDOException $exception) {
            $this->assertTrue($isDuplicate->invoke($service, $exception));
        }
    }

    public function testSubmitReportReturnsBusyMessageWhenLockIsUnavailable(): void
    {
        $service = new PlayerReportService($this->pdo, new UnavailableIpSubmissionLockExecutor());
        $result = $service->submitReport(123, '198.51.100.10', 'Cheating behavior');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('busy', strtolower($result->getMessage()));
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM player_report')->fetchColumn());
    }

    public function testSubmitReportPerformsDuplicateCheckInsideIpLock(): void
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/classes/PlayerReportService.php');
        $this->assertTrue(is_string($source));

        $methodStart = strpos($source, 'public function submitReport');
        $this->assertTrue($methodStart !== false);

        $methodSource = substr($source, $methodStart, 1200);
        $lockPos = strpos($methodSource, 'getIpSubmissionLockExecutor()->execute');
        $duplicatePos = strpos($methodSource, 'hasExistingReport($accountId, $ipAddress)');

        $this->assertTrue($lockPos !== false);
        $this->assertTrue($duplicatePos !== false);
        $this->assertTrue($duplicatePos > $lockPos);
    }
}

final class UnavailableIpSubmissionLockExecutor extends IpSubmissionLockExecutor
{
    public function __construct()
    {
        parent::__construct(new PDO('sqlite::memory:'));
    }

    public function execute(string $ipAddress, callable $callback): mixed
    {
        throw new IpSubmissionLockUnavailableException('Unable to acquire IP submission lock.');
    }
}
