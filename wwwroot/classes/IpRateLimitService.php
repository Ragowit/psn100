<?php

declare(strict_types=1);

/**
 * Fixed-window IP rate limiting backed by the ip_rate_limit table.
 */
final class IpRateLimitService
{
    public const string BUCKET_QUEUE_POLL = 'queue_poll';

    public const string BUCKET_SCAN_LOG_POLL = 'scan_log_poll';

    private const int QUEUE_POLL_MAX_REQUESTS = 60;

    private const int QUEUE_POLL_WINDOW_SECONDS = 60;

    private const int SCAN_LOG_POLL_MAX_REQUESTS = 30;

    private const int SCAN_LOG_POLL_WINDOW_SECONDS = 60;

    public function __construct(private readonly PDO $database)
    {
    }

    public function isAllowed(string $ipAddress, string $bucket): bool
    {
        if ($ipAddress === '') {
            return true;
        }

        [$maxRequests, $windowSeconds] = $this->resolveLimits($bucket);
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

    public function recordRequest(string $ipAddress, string $bucket): void
    {
        if ($ipAddress === '') {
            return;
        }

        [$maxRequests, $windowSeconds] = $this->resolveLimits($bucket);
        $bucketKey = $this->buildBucketKey($ipAddress, $bucket);
        $row = $this->fetchBucketRow($bucketKey);
        $now = $this->formatTimestamp($this->clock());

        if ($row === null) {
            $this->insertBucketRow($bucketKey, $now, 1);

            return;
        }

        $windowStart = $this->parseTimestamp($row['window_start'] ?? null);
        $requestCount = (int) ($row['request_count'] ?? 0);

        if ($windowStart === null || $this->hasWindowExpired($windowStart, $windowSeconds)) {
            $this->updateBucketRow($bucketKey, $now, 1);

            return;
        }

        if ($requestCount >= $maxRequests) {
            return;
        }

        $this->updateBucketRow($bucketKey, $this->formatTimestamp($windowStart), $requestCount + 1);
    }

    public function checkAndRecord(string $ipAddress, string $bucket): bool
    {
        if (!$this->isAllowed($ipAddress, $bucket)) {
            return false;
        }

        $this->recordRequest($ipAddress, $bucket);

        return true;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveLimits(string $bucket): array
    {
        return match ($bucket) {
            self::BUCKET_SCAN_LOG_POLL => [
                self::SCAN_LOG_POLL_MAX_REQUESTS,
                self::SCAN_LOG_POLL_WINDOW_SECONDS,
            ],
            self::BUCKET_QUEUE_POLL => [
                self::QUEUE_POLL_MAX_REQUESTS,
                self::QUEUE_POLL_WINDOW_SECONDS,
            ],
            default => throw new InvalidArgumentException(sprintf('Unknown rate-limit bucket "%s".', $bucket)),
        };
    }

    private function buildBucketKey(string $ipAddress, string $bucket): string
    {
        return hash('sha256', $bucket . '|' . $ipAddress);
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
        if ($this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
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

            return;
        }

        $statement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO ip_rate_limit (bucket_key, window_start, request_count)
            VALUES (:bucket_key, :window_start, :request_count) AS new_values
            ON DUPLICATE KEY UPDATE
                window_start = new_values.window_start,
                request_count = new_values.request_count
            SQL
        );
        $statement->bindValue(':bucket_key', $bucketKey, PDO::PARAM_STR);
        $statement->bindValue(':window_start', $windowStart, PDO::PARAM_STR);
        $statement->bindValue(':request_count', $requestCount, PDO::PARAM_INT);
        $statement->execute();
    }

    private function hasWindowExpired(DateTimeImmutable $windowStart, int $windowSeconds): bool
    {
        return $this->clock()->getTimestamp() - $windowStart->getTimestamp() >= $windowSeconds;
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
