<?php

declare(strict_types=1);

final class AdminLoginThrottleService
{
    public const int MAX_FAILURES = 5;

    public const int LOCK_SECONDS = 900;

    public function __construct(private readonly PDO $database)
    {
    }

    public function isLocked(string $ipAddress): bool
    {
        return $this->getLockoutRemainingSeconds($ipAddress) > 0;
    }

    public function getLockoutRemainingSeconds(string $ipAddress): int
    {
        if ($ipAddress === '') {
            return 0;
        }

        $row = $this->fetchRow($ipAddress);
        if ($row === null) {
            return 0;
        }

        $lockedUntil = $this->parseTimestamp($row['locked_until'] ?? null);
        if ($lockedUntil === null) {
            return 0;
        }

        $remaining = $lockedUntil->getTimestamp() - $this->clock()->getTimestamp();

        return $remaining > 0 ? $remaining : 0;
    }

    public function recordFailure(string $ipAddress): void
    {
        if ($ipAddress === '') {
            return;
        }

        $lastAttemptAt = $this->formatTimestamp($this->clock());

        if ($this->isSqlite()) {
            $this->recordFailureSqlite($ipAddress, $lastAttemptAt);

            return;
        }

        $this->recordFailureMysql($ipAddress, $lastAttemptAt);
    }

    public function recordSuccess(string $ipAddress): void
    {
        if ($ipAddress === '') {
            return;
        }

        $statement = $this->database->prepare(
            'DELETE FROM admin_login_throttle WHERE ip_address = :ip_address'
        );
        $statement->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $statement->execute();
    }

  /**
   * @return array<string, mixed>|null
   */
    private function fetchRow(string $ipAddress): ?array
    {
        $statement = $this->database->prepare(
            <<<'SQL'
            SELECT
                failure_count,
                locked_until
            FROM
                admin_login_throttle
            WHERE
                ip_address = :ip_address
            LIMIT 1
            SQL
        );
        $statement->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function recordFailureMysql(string $ipAddress, string $lastAttemptAt): void
    {
        $statement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO admin_login_throttle (ip_address, failure_count, locked_until, last_attempt_at)
            VALUES (:ip_address, 1, NULL, :last_attempt_at)
            AS new_values
            ON DUPLICATE KEY UPDATE
                failure_count = (@new_failure_count := admin_login_throttle.failure_count + 1),
                last_attempt_at = new_values.last_attempt_at,
                locked_until = IF(
                    @new_failure_count >= :max_failures,
                    CURRENT_TIMESTAMP + INTERVAL :lock_seconds SECOND,
                    admin_login_throttle.locked_until
                )
            SQL
        );
        $statement->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $statement->bindValue(':last_attempt_at', $lastAttemptAt, PDO::PARAM_STR);
        $statement->bindValue(':max_failures', self::MAX_FAILURES, PDO::PARAM_INT);
        $statement->bindValue(':lock_seconds', self::LOCK_SECONDS, PDO::PARAM_INT);
        $statement->execute();
    }

    private function recordFailureSqlite(string $ipAddress, string $lastAttemptAt): void
    {
        $lockedUntil = $this->formatTimestamp(
            $this->clock()->modify('+' . self::LOCK_SECONDS . ' seconds')
        );

        $statement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO admin_login_throttle (ip_address, failure_count, locked_until, last_attempt_at)
            VALUES (:ip_address, 1, NULL, :last_attempt_at)
            ON CONFLICT(ip_address) DO UPDATE SET
                failure_count = failure_count + 1,
                last_attempt_at = excluded.last_attempt_at,
                locked_until = CASE
                    WHEN failure_count + 1 >= :max_failures THEN :locked_until
                    ELSE locked_until
                END
            SQL
        );
        $statement->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $statement->bindValue(':last_attempt_at', $lastAttemptAt, PDO::PARAM_STR);
        $statement->bindValue(':max_failures', self::MAX_FAILURES, PDO::PARAM_INT);
        $statement->bindValue(':locked_until', $lockedUntil, PDO::PARAM_STR);
        $statement->execute();
    }

    private function isSqlite(): bool
    {
        return $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function parseTimestamp(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private function formatTimestamp(DateTimeImmutable $timestamp): string
    {
        return $timestamp->format('Y-m-d H:i:s');
    }

    private function clock(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}
