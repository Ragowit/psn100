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

        $row = $this->fetchRow($ipAddress);
        $now = $this->formatTimestamp($this->clock());
        $failureCount = $row === null ? 1 : (int) ($row['failure_count'] ?? 0) + 1;
        $lockedUntil = null;

        if ($failureCount >= self::MAX_FAILURES) {
            $lockedUntil = $this->formatTimestamp(
                $this->clock()->modify('+' . self::LOCK_SECONDS . ' seconds')
            );
        }

        if ($row === null) {
            $this->insertRow($ipAddress, $failureCount, $lockedUntil, $now);

            return;
        }

        $this->updateRow($ipAddress, $failureCount, $lockedUntil, $now);
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

    private function insertRow(
        string $ipAddress,
        int $failureCount,
        ?string $lockedUntil,
        string $lastAttemptAt,
    ): void {
        $statement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO admin_login_throttle (ip_address, failure_count, locked_until, last_attempt_at)
            VALUES (:ip_address, :failure_count, :locked_until, :last_attempt_at)
            SQL
        );
        $statement->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $statement->bindValue(':failure_count', $failureCount, PDO::PARAM_INT);
        $this->bindNullableTimestamp($statement, ':locked_until', $lockedUntil);
        $statement->bindValue(':last_attempt_at', $lastAttemptAt, PDO::PARAM_STR);
        $statement->execute();
    }

    private function updateRow(
        string $ipAddress,
        int $failureCount,
        ?string $lockedUntil,
        string $lastAttemptAt,
    ): void {
        $statement = $this->database->prepare(
            <<<'SQL'
            UPDATE admin_login_throttle
            SET
                failure_count = :failure_count,
                locked_until = :locked_until,
                last_attempt_at = :last_attempt_at
            WHERE
                ip_address = :ip_address
            SQL
        );
        $statement->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $statement->bindValue(':failure_count', $failureCount, PDO::PARAM_INT);
        $this->bindNullableTimestamp($statement, ':locked_until', $lockedUntil);
        $statement->bindValue(':last_attempt_at', $lastAttemptAt, PDO::PARAM_STR);
        $statement->execute();
    }

    private function bindNullableTimestamp(PDOStatement $statement, string $parameter, ?string $value): void
    {
        if ($value === null) {
            $statement->bindValue($parameter, null, PDO::PARAM_NULL);

            return;
        }

        $statement->bindValue($parameter, $value, PDO::PARAM_STR);
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
