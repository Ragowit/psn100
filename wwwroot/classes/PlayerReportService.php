<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerReportResult.php';
require_once __DIR__ . '/IpSubmissionLockExecutor.php';
require_once __DIR__ . '/IpSubmissionLockUnavailableException.php';

class PlayerReportService
{
    private const int MAX_PENDING_REPORTS_PER_IP = 10;

    public const int MAX_EXPLANATION_LENGTH = 256;

    private const string SUBMIT_OUTCOME_SUCCESS = 'success';

    private const string SUBMIT_OUTCOME_DUPLICATE = 'duplicate';

    private const string SUBMIT_OUTCOME_LIMIT = 'limit';

    public function __construct(
        private readonly PDO $database,
        private readonly ?IpSubmissionLockExecutor $ipSubmissionLockExecutor = null,
    ) {
    }

    public function submitReport(int $accountId, string $ipAddress, string $explanation): PlayerReportResult
    {
        if (mb_strlen($explanation) > self::MAX_EXPLANATION_LENGTH) {
            return PlayerReportResult::error(
                'Explanation must be ' . self::MAX_EXPLANATION_LENGTH . ' characters or fewer.'
            );
        }

        try {
            $outcome = $this->getIpSubmissionLockExecutor()->execute(
                $ipAddress,
                function () use ($accountId, $ipAddress, $explanation): string {
                    if ($this->hasExistingReport($accountId, $ipAddress)) {
                        return self::SUBMIT_OUTCOME_DUPLICATE;
                    }

                    if ($this->getReportCountForIp($ipAddress) >= self::MAX_PENDING_REPORTS_PER_IP) {
                        return self::SUBMIT_OUTCOME_LIMIT;
                    }

                    try {
                        $this->insertReport($accountId, $ipAddress, $explanation);
                    } catch (PDOException $exception) {
                        if ($this->isDuplicateReportException($exception)) {
                            return self::SUBMIT_OUTCOME_DUPLICATE;
                        }

                        throw $exception;
                    }

                    return self::SUBMIT_OUTCOME_SUCCESS;
                }
            );
        } catch (IpSubmissionLockUnavailableException) {
            return PlayerReportResult::error(
                'The server is busy processing another request. Please try again in a moment.'
            );
        }

        return match ($outcome) {
            self::SUBMIT_OUTCOME_SUCCESS => PlayerReportResult::success('Player reported successfully.'),
            self::SUBMIT_OUTCOME_DUPLICATE => PlayerReportResult::error("You've already reported this player."),
            self::SUBMIT_OUTCOME_LIMIT => PlayerReportResult::error(
                'You already have 10 reports waiting to be processed. Please try again later.'
            ),
        };
    }

    private function getIpSubmissionLockExecutor(): IpSubmissionLockExecutor
    {
        if ($this->ipSubmissionLockExecutor !== null) {
            return $this->ipSubmissionLockExecutor;
        }

        return new IpSubmissionLockExecutor($this->database);
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

    private function isDuplicateReportException(PDOException $exception): bool
    {
        if ($exception->getCode() === '23000') {
            return true;
        }

        $message = $exception->getMessage();

        return str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry');
    }
}
