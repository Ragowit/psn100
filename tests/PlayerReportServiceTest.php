<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerReportService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerReportResult.php';

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
                explanation TEXT NOT NULL
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
            "You've already 10 players reported waiting to be processed. Please try again later.",
            $result->getMessage()
        );

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM player_report')->fetchColumn();
        $this->assertSame(10, $count);
    }
}
