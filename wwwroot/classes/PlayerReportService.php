<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerReportResult.php';

class PlayerReportService
{
    private const MAX_PENDING_REPORTS_PER_IP = 10;

    public function __construct(private readonly PDO $database)
    {
    }

    public function submitReport(int $accountId, string $ipAddress, string $explanation): PlayerReportResult
    {
        if ($this->hasExistingReport($accountId, $ipAddress)) {
            return PlayerReportResult::error("You've already reported this player.");
        }

        if ($this->getReportCountForIp($ipAddress) >= self::MAX_PENDING_REPORTS_PER_IP) {
            return PlayerReportResult::error("You've already 10 players reported waiting to be processed. Please try again later.");
        }

        $this->insertReport($accountId, $ipAddress, $explanation);

        return PlayerReportResult::success('Player reported successfully.');
    }

    private function hasExistingReport(int $accountId, string $ipAddress): bool
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                1
            FROM
                player_report
            WHERE
                account_id = :account_id
                AND ip_address = :ip_address
            LIMIT 1
            SQL
        );
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $query->execute();

        return $query->fetchColumn() !== false;
    }

    private function getReportCountForIp(string $ipAddress): int
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                COUNT(*)
            FROM
                player_report
            WHERE
                ip_address = :ip_address
            SQL
        );
        $query->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    private function insertReport(int $accountId, string $ipAddress, string $explanation): void
    {
        $query = $this->database->prepare(
            <<<'SQL'
            INSERT INTO player_report
                (account_id, ip_address, explanation)
            VALUES
                (:account_id, :ip_address, :explanation)
            SQL
        );
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $query->bindValue(':explanation', $explanation, PDO::PARAM_STR);
        $query->execute();
    }
}
