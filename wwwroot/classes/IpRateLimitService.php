<?php

declare(strict_types=1);

require_once __DIR__ . '/IpAddressResolver.php';
require_once __DIR__ . '/IpRateLimitBucket.php';

/**
 * Fixed-window IP rate limiting backed by the ip_rate_limit table.
 */
final class IpRateLimitService
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function isAllowed(string $ipAddress, IpRateLimitBucket $bucket): bool
    {
        [$maxRequests, $windowSeconds] = $bucket->limits();
        $bucketKey = $this->buildBucketKey($ipAddress, $bucket);
        $row = $this->fetchBucketRow($bucketKey);

        if ($row === null) {
            return true;
        }

        $windowStart = $this->parseTimestamp($row['window_start'] ?? null);
        $requestCount = (int) ($row['request_count'] ?? 0);

        if ($windowStart === null || $this->hasWindowExpired($windowStart, $windowSeconds)) {
            return true;
        }

        return $requestCount < $maxRequests;
    }

    public function recordRequest(string $ipAddress, IpRateLimitBucket $bucket): void
    {
        $this->checkAndRecord($ipAddress, $bucket);
    }

    public function checkAndRecord(string $ipAddress, IpRateLimitBucket $bucket): bool
    {
        [$maxRequests, $windowSeconds] = $bucket->limits();
        $bucketKey = $this->buildBucketKey($ipAddress, $bucket);

        $startedTransaction = false;
        if (!$this->database->inTransaction()) {
            $this->database->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $allowed = $this->consumeSlotIfAvailable($bucketKey, $maxRequests, $windowSeconds);

            if ($startedTransaction) {
                $this->database->commit();
            }

            return $allowed;
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    private function buildBucketKey(string $ipAddress, IpRateLimitBucket $bucket): string
    {
        $normalizedIpAddress = IpAddressResolver::normalizeForAbuseControls($ipAddress);

        return hash('sha256', $bucket->value . '|' . $normalizedIpAddress);
    }

    private function consumeSlotIfAvailable(string $bucketKey, int $maxRequests, int $windowSeconds): bool
    {
        $row = $this->fetchBucketRowForUpdate($bucketKey);
        $now = $this->clock();

        if ($row === null) {
            try {
                $this->insertBucketRow($bucketKey, $this->formatTimestamp($now), 1);

                return true;
            } catch (PDOException $exception) {
                if (!$this->isDuplicateKeyException($exception)) {
                    throw $exception;
                }

                $row = $this->fetchBucketRowForUpdate($bucketKey);
            }
        }

        if ($row === null) {
            return false;
        }

        $windowStart = $this->parseTimestamp($row['window_start'] ?? null);
        $requestCount = (int) ($row['request_count'] ?? 0);

        if ($windowStart === null || $this->hasWindowExpired($windowStart, $windowSeconds, $now)) {
            $this->updateBucketRow($bucketKey, $this->formatTimestamp($now), 1);

            return true;
        }

        if ($requestCount >= $maxRequests) {
            return false;
        }

        $this->updateBucketRow($bucketKey, $this->formatTimestamp($windowStart), $requestCount + 1);

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchBucketRow(string $bucketKey): ?array
    {
        $statement = $this->database->prepare(
            <<<'SQL'
            SELECT
                window_start,
                request_count
            FROM
                ip_rate_limit
            WHERE
                bucket_key = :bucket_key
            LIMIT 1
            SQL
        );
        $statement->bindValue(':bucket_key', $bucketKey, PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchBucketRowForUpdate(string $bucketKey): ?array
    {
        if ($this->isSqlite()) {
            return $this->fetchBucketRow($bucketKey);
        }

        $statement = $this->database->prepare(
            <<<'SQL'
            SELECT
                window_start,
                request_count
            FROM
                ip_rate_limit
            WHERE
                bucket_key = :bucket_key
            LIMIT 1
            FOR UPDATE
            SQL
        );
        $statement->bindValue(':bucket_key', $bucketKey, PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function insertBucketRow(string $bucketKey, string $windowStart, int $requestCount): void
    {
        $statement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO ip_rate_limit (bucket_key, window_start, request_count)
            VALUES (:bucket_key, :window_start, :request_count)
            SQL
        );
        $statement->bindValue(':bucket_key', $bucketKey, PDO::PARAM_STR);
        $statement->bindValue(':window_start', $windowStart, PDO::PARAM_STR);
        $statement->bindValue(':request_count', $requestCount, PDO::PARAM_INT);
        $statement->execute();
    }

    private function updateBucketRow(string $bucketKey, string $windowStart, int $requestCount): void
    {
        $statement = $this->database->prepare(
            <<<'SQL'
            UPDATE ip_rate_limit
            SET
                window_start = :window_start,
                request_count = :request_count
            WHERE
                bucket_key = :bucket_key
            SQL
        );
        $statement->bindValue(':bucket_key', $bucketKey, PDO::PARAM_STR);
        $statement->bindValue(':window_start', $windowStart, PDO::PARAM_STR);
        $statement->bindValue(':request_count', $requestCount, PDO::PARAM_INT);
        $statement->execute();
    }

    private function hasWindowExpired(
        DateTimeImmutable $windowStart,
        int $windowSeconds,
        ?DateTimeImmutable $now = null,
    ): bool {
        $now ??= $this->clock();

        return $now->getTimestamp() - $windowStart->getTimestamp() >= $windowSeconds;
    }

    private function isDuplicateKeyException(PDOException $exception): bool
    {
        if ($exception->getCode() === '23000') {
            return true;
        }

        $message = $exception->getMessage();

        return str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry');
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
