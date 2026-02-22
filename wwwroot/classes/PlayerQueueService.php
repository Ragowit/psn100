<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerScanProgress.php';
require_once __DIR__ . '/PlayerScanStatus.php';

class PlayerQueueService
{
    public const int MAX_QUEUE_SUBMISSIONS_PER_IP = 10;
    public const int CHEATER_STATUS = 1;
    private const string PLAYER_NAME_PATTERN = '/^[\\w\-]{3,16}$/';

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

        $count = $this->fetchSingleValue(
            <<<'SQL'
            SELECT
                COUNT(*)
            FROM
                player_queue
            WHERE
                ip_address = :ip_address
            SQL,
            [':ip_address' => [$ipAddress, PDO::PARAM_STR]]
        );

        return (int) ($count ?? 0);
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

        $accountId = $this->fetchSingleValue(
            <<<'SQL'
            SELECT
                account_id
            FROM
                player
            WHERE
                online_id = :online_id
                AND status = :status
            SQL,
            [
                ':online_id' => [$playerName, PDO::PARAM_STR],
                ':status' => [self::CHEATER_STATUS, PDO::PARAM_INT],
            ]
        );

        return $accountId === false ? null : (string) $accountId;
    }

    public function addPlayerToQueue(string $playerName, string $ipAddress): void
    {
        $query = $this->prepareAndBind(
            <<<'SQL'
            INSERT INTO
                player_queue (online_id, ip_address)
            VALUES
                (:online_id, :ip_address) AS new_submission
            ON DUPLICATE KEY UPDATE
                online_id = new_submission.online_id
            SQL,
            [
                ':online_id' => [$playerName, PDO::PARAM_STR],
                ':ip_address' => [$ipAddress, PDO::PARAM_STR],
            ]
        );
        $query->execute();
    }

    public function isValidPlayerName(string $playerName): bool
    {
        return preg_match(self::PLAYER_NAME_PATTERN, $playerName) === 1;
    }

    public function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

        $row = $this->fetchAssoc(
            <<<'SQL'
            SELECT
                scan_progress
            FROM
                setting
            WHERE
                scanning = :online_id
            SQL,
            [':online_id' => [$playerName, PDO::PARAM_STR]]
        );

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

        $position = $this->fetchSingleValue(
            <<<'SQL'
            WITH target AS (
                SELECT
                    request_time,
                    online_id
                FROM
                    player_queue
                WHERE
                    online_id = :online_id
                LIMIT 1
            )
            SELECT
                (
                    SELECT
                        COUNT(*) + 1
                    FROM
                        player_queue queue_entry
                    WHERE
                        (queue_entry.request_time, queue_entry.online_id) < (target.request_time, target.online_id)
                ) AS position
            FROM
                target
            SQL,
            [':online_id' => [$playerName, PDO::PARAM_STR]]
        );

        return $position === false ? null : (int) $position;
    }

    /**
     * @return array{account_id: string|null, status: int|null}|null
     */
    public function getPlayerStatusData(string $playerName): ?array
    {
        $result = $this->fetchAssoc(
            <<<'SQL'
            SELECT
                account_id,
                `status`
            FROM
                player
            WHERE
                online_id = :online_id
            SQL,
            [':online_id' => [$playerName, PDO::PARAM_STR]]
        );

        if (!is_array($result)) {
            return null;
        }

        return [
            'account_id' => $this->toOptionalString($result, 'account_id'),
            'status' => $this->toOptionalInt($result, 'status'),
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

    /**
     * @param array<string, array{0: scalar|null, 1: int}> $bindings
     */
    private function prepareAndBind(string $sql, array $bindings): PDOStatement
    {
        $statement = $this->requireDatabase()->prepare($sql);

        foreach ($bindings as $parameter => [$value, $type]) {
            $statement->bindValue($parameter, $value, $type);
        }

        return $statement;
    }

    /**
     * @param array<string, array{0: scalar|null, 1: int}> $bindings
     * @return scalar|null|false
     */
    private function fetchSingleValue(string $sql, array $bindings): mixed
    {
        $statement = $this->prepareAndBind($sql, $bindings);
        $statement->execute();

        return $statement->fetchColumn();
    }

    /**
     * @param array<string, array{0: scalar|null, 1: int}> $bindings
     * @return array<string, mixed>|false
     */
    private function fetchAssoc(string $sql, array $bindings): array|false
    {
        $statement = $this->prepareAndBind($sql, $bindings);
        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toOptionalString(array $row, string $key): ?string
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return null;
        }

        return (string) $row[$key];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toOptionalInt(array $row, string $key): ?int
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return null;
        }

        return (int) $row[$key];
    }
}
