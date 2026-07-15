<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerScanProgress.php';
require_once __DIR__ . '/PlayerScanStatus.php';
require_once __DIR__ . '/IpSubmissionLockExecutor.php';
require_once __DIR__ . '/IpSubmissionLockUnavailableException.php';
require_once __DIR__ . '/Html.php';

class PlayerQueueService
{
    public const int MAX_QUEUE_SUBMISSIONS_PER_IP = 10;
    public const int CHEATER_STATUS = 1;
    public const string ONLINE_ID_HTML_PATTERN = '[A-Za-z][A-Za-z0-9_-]{2,15}';
    public const string INVALID_ONLINE_ID_MESSAGE = 'PSN name must contain between three and 16 characters, start with a letter, and can consist of letters, numbers, hyphens (-) and underscores (_). Letters are not case-sensitive.';
    private const string ONLINE_ID_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_-]{2,15}$/';
    private const string SQL_IP_SUBMISSION_COUNT = <<<'SQL'
        SELECT
            COUNT(*)
        FROM
            player_queue
        WHERE
            ip_address = :ip_address
        SQL;
    private const string SQL_CHEATER_ACCOUNT_ID = <<<'SQL'
        SELECT
            account_id
        FROM
            player
        WHERE
            online_id = :online_id
            AND status = :status
        SQL;
    private const string SQL_ACTIVE_SCAN_STATUS = <<<'SQL'
        SELECT
            scan_progress
        FROM
            setting
        WHERE
            scanning = :online_id
        SQL;
    private const string SQL_QUEUE_POSITION = <<<'SQL'
        WITH ordered_queue AS (
            SELECT
                online_id,
                ROW_NUMBER() OVER (ORDER BY request_time, online_id) AS position
            FROM
                player_queue
        )
        SELECT
            position
        FROM
            ordered_queue
        WHERE
            online_id = :online_id
        LIMIT 1
        SQL;
    private const string SQL_PLAYER_IN_QUEUE = <<<'SQL'
        SELECT
            1
        FROM
            player_queue
        WHERE
            online_id = :online_id
        LIMIT 1
        SQL;
    private const string SQL_PLAYER_STATUS_DATA = <<<'SQL'
        SELECT
            account_id,
            `status`
        FROM
            player
        WHERE
            online_id = :online_id
        SQL;

    public function __construct(
        private readonly ?PDO $database = null,
        private readonly ?IpSubmissionLockExecutor $ipSubmissionLockExecutor = null,
    ) {
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
        $count = $this->fetchSingleValue(
            self::SQL_IP_SUBMISSION_COUNT,
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
            self::SQL_CHEATER_ACCOUNT_ID,
            [
                ':online_id' => [$playerName, PDO::PARAM_STR],
                ':status' => [self::CHEATER_STATUS, PDO::PARAM_INT],
            ]
        );

        return $accountId === false ? null : (string) $accountId;
    }

    public function addPlayerToQueue(string $playerName, string $ipAddress): bool
    {
        return $this->getIpSubmissionLockExecutor()->execute(
            $ipAddress,
            function () use ($playerName, $ipAddress): bool {
                if (
                    !$this->isPlayerInQueue($playerName)
                    && $this->hasReachedIpSubmissionLimit($ipAddress)
                ) {
                    return false;
                }

                $this->executeAddPlayerToQueueInsert($playerName, $ipAddress);

                return true;
            }
        );
    }

    public function isPlayerInQueue(string $playerName): bool
    {
        if ($playerName === '') {
            return false;
        }

        $exists = $this->fetchSingleValue(
            self::SQL_PLAYER_IN_QUEUE,
            [':online_id' => [$playerName, PDO::PARAM_STR]]
        );

        return $exists !== false;
    }

    private function executeAddPlayerToQueueInsert(string $playerName, string $ipAddress): void
    {
        if ($this->requireDatabase()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $query = $this->prepareAndBind(
                'INSERT OR IGNORE INTO player_queue (online_id, ip_address) VALUES (:online_id, :ip_address)',
                [
                    ':online_id' => [$playerName, PDO::PARAM_STR],
                    ':ip_address' => [$ipAddress, PDO::PARAM_STR],
                ]
            );
            $query->execute();

            return;
        }

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

    private function getIpSubmissionLockExecutor(): IpSubmissionLockExecutor
    {
        if ($this->ipSubmissionLockExecutor !== null) {
            return $this->ipSubmissionLockExecutor;
        }

        return new IpSubmissionLockExecutor($this->requireDatabase());
    }

    public static function isValidOnlineId(string $playerName): bool
    {
        return $playerName !== '' && preg_match(self::ONLINE_ID_PATTERN, $playerName) === 1;
    }

    public function isValidPlayerName(string $playerName): bool
    {
        return self::isValidOnlineId($playerName);
    }

    public function escapeHtml(string $value): string
    {
        return Html::escape($value);
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
            self::SQL_ACTIVE_SCAN_STATUS,
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
            self::SQL_QUEUE_POSITION,
            [':online_id' => [$playerName, PDO::PARAM_STR]]
        );

        if ($position === false) {
            return null;
        }

        return (int) $position;
    }

    /**
     * @return array{account_id: string|null, status: int|null}|null
     */
    public function getPlayerStatusData(string $playerName): ?array
    {
        $result = $this->fetchAssoc(
            self::SQL_PLAYER_STATUS_DATA,
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
