<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerScanProgress.php';
require_once __DIR__ . '/PlayerScanStatus.php';

class PlayerQueueService
{
    public const int MAX_QUEUE_SUBMISSIONS_PER_IP = 10;
    public const int CHEATER_STATUS = 1;

    public function __construct(private readonly ?PDO $database = null)
    {
    }

    private function requireDatabase(): PDO
    {
        if ($this->database === null) {
            throw new LogicException('PlayerQueueService requires a database connection.');
        }

        return $this->database;
    }

    public function getIpSubmissionCount(string $ipAddress): int
    {
        if ($ipAddress === '') {
            return 0;
        }

        $query = $this->requireDatabase()->prepare(
            <<<'SQL'
            SELECT
                COUNT(*)
            FROM
                player_queue
            WHERE
                ip_address = :ip_address
            SQL
        );
        $query->bindValue(":ip_address", $ipAddress, PDO::PARAM_STR);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    public function hasReachedIpSubmissionLimit(string $ipAddress): bool
    {
        return $this->getIpSubmissionCount($ipAddress) >= self::MAX_QUEUE_SUBMISSIONS_PER_IP;
    }

    public function getCheaterAccountId(string $playerName): ?string
    {
        if ($playerName === '') {
            return null;
        }

        $query = $this->requireDatabase()->prepare(
            <<<'SQL'
            SELECT
                account_id
            FROM
                player
            WHERE
                online_id = :online_id
                AND status = :status
            SQL
        );
        $query->bindValue(":online_id", $playerName, PDO::PARAM_STR);
        $query->bindValue(":status", self::CHEATER_STATUS, PDO::PARAM_INT);
        $query->execute();

        $accountId = $query->fetchColumn();

        return $accountId === false ? null : (string) $accountId;
    }

    public function addPlayerToQueue(string $playerName, string $ipAddress): void
    {
        $query = $this->requireDatabase()->prepare(
            <<<'SQL'
            INSERT IGNORE INTO
                player_queue (online_id, ip_address)
            VALUES
                (:online_id, :ip_address)
            SQL
        );
        $query->bindValue(":online_id", $playerName, PDO::PARAM_STR);
        $query->bindValue(":ip_address", $ipAddress, PDO::PARAM_STR);
        $query->execute();
    }

    public function isValidPlayerName(string $playerName): bool
    {
        return preg_match('/^[\\w\-]{3,16}$/', $playerName) === 1;
    }

    public function escapeHtml(string $value): string
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8');
    }

    public function isPlayerBeingScanned(string $playerName): bool
    {
        return $this->getActiveScanStatus($playerName) !== null;
    }

    /**
     * Returns information about the current scan for the provided player, if any.
     */
    public function getActiveScanStatus(string $playerName): ?PlayerScanStatus
    {
        if ($playerName === '') {
            return null;
        }

        $query = $this->requireDatabase()->prepare(
            <<<'SQL'
            SELECT
                scan_progress
            FROM
                setting
            WHERE
                scanning = :online_id
            SQL
        );
        $query->bindValue(":online_id", $playerName, PDO::PARAM_STR);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return PlayerScanStatus::withProgress(
            $this->decodeScanProgress($row['scan_progress'] ?? null)
        );
    }

    public function getActiveScanProgress(string $playerName): ?PlayerScanProgress
    {
        $status = $this->getActiveScanStatus($playerName);

        return $status?->getProgress();
    }

    public function getQueuePosition(string $playerName): ?int
    {
        if ($playerName === '') {
            return null;
        }

        $positionQuery = $this->requireDatabase()->prepare(
            <<<'SQL'
            SELECT
                position
            FROM (
                SELECT
                    online_id,
                    ROW_NUMBER() OVER (ORDER BY request_time, online_id) AS position
                FROM
                    player_queue
            ) ranked
            WHERE
                ranked.online_id = :online_id
            SQL
        );
        $positionQuery->bindValue(':online_id', $playerName, PDO::PARAM_STR);
        $positionQuery->execute();

        $position = $positionQuery->fetchColumn();

        return $position === false ? null : (int) $position;
    }

    /**
     * @return array{account_id: string|null, status: int|null}|null
     */
    public function getPlayerStatusData(string $playerName): ?array
    {
        $query = $this->requireDatabase()->prepare(
            <<<'SQL'
            SELECT
                account_id,
                `status`
            FROM
                player
            WHERE
                online_id = :online_id
            SQL
        );
        $query->bindValue(":online_id", $playerName, PDO::PARAM_STR);
        $query->execute();

        $result = $query->fetch(PDO::FETCH_ASSOC);

        if (!is_array($result)) {
            return null;
        }

        return [
            'account_id' => array_key_exists('account_id', $result) && $result['account_id'] !== null
                ? (string) $result['account_id']
                : null,
            'status' => array_key_exists('status', $result) && $result['status'] !== null
                ? (int) $result['status']
                : null,
        ];
    }

    private function decodeScanProgress(?string $value): ?PlayerScanProgress
    {
        if ($value === null || $value === '') {
            return null;
        }

        return PlayerScanProgress::fromJson($value);
    }

    public function isCheaterStatus(?int $status): bool
    {
        return $status === self::CHEATER_STATUS;
    }
}
