<?php

declare(strict_types=1);

class PlayerQueueService
{
    public const MAX_QUEUE_SUBMISSIONS_PER_IP = 10;
    public const CHEATER_STATUS = 1;

    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
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
        $requestTimeQuery = $this->database->prepare(
            <<<'SQL'
            SELECT
                request_time
            FROM
                player_queue
            WHERE
                online_id = :online_id
            SQL
        );
        $requestTimeQuery->bindValue(':online_id', $playerName, PDO::PARAM_STR);
        $requestTimeQuery->execute();

        $requestTime = $requestTimeQuery->fetchColumn();

        if ($requestTime === false) {
            return null;
        }

        $positionQuery = $this->database->prepare(
            <<<'SQL'
            SELECT
                COUNT(*)
            FROM
                player_queue pq
            WHERE
                pq.request_time < :request_time
                OR (
                    pq.request_time = :request_time
                    AND pq.online_id <= :online_id
                )
            SQL
        );
        $positionQuery->bindValue(':request_time', $requestTime, PDO::PARAM_STR);
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

    public function isCheaterStatus(?int $status): bool
    {
        return $status === self::CHEATER_STATUS;
    }
}
