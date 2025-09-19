<?php

class PlayerQueueService
{
    public const MAX_QUEUE_SUBMISSIONS_PER_IP = 10;
    public const CHEATER_STATUS = 1;

    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function sanitizePlayerName(?string $playerName): string
    {
        return trim((string) ($playerName ?? ''));
    }

    public function sanitizeIpAddress(?string $ipAddress): string
    {
        return trim((string) ($ipAddress ?? ''));
    }

    public function getIpSubmissionCount(string $ipAddress): int
    {
        if ($ipAddress === '') {
            return 0;
        }

        $query = $this->database->prepare(
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

        $query = $this->database->prepare(
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
        $query = $this->database->prepare(
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
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                scanning
            FROM
                setting
            WHERE
                scanning = :online_id
            SQL
        );
        $query->bindValue(":online_id", $playerName, PDO::PARAM_STR);
        $query->execute();

        return $query->fetchColumn() !== false;
    }

    public function getQueuePosition(string $playerName): ?int
    {
        $query = $this->database->prepare(
            <<<'SQL'
            WITH temp AS (
                SELECT
                    request_time,
                    online_id,
                    ROW_NUMBER() OVER (
                        ORDER BY
                            request_time
                    ) AS rownum
                FROM
                    player_queue
            )
            SELECT
                rownum
            FROM
                temp
            WHERE
                online_id = :online_id
            SQL
        );
        $query->bindValue(":online_id", $playerName, PDO::PARAM_STR);
        $query->execute();

        $position = $query->fetchColumn();

        return $position === false ? null : (int) $position;
    }

    public function getPlayerStatusData(string $playerName): array
    {
        $query = $this->database->prepare(
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
            return [
                'account_id' => null,
                'status' => null,
            ];
        }

        return [
            'account_id' => array_key_exists('account_id', $result) ? (string) $result['account_id'] : null,
            'status' => array_key_exists('status', $result) ? (int) $result['status'] : null,
        ];
    }

    public function isCheaterStatus(?int $status): bool
    {
        return $status === self::CHEATER_STATUS;
    }
}
