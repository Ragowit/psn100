<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/IpRateLimitService.php';

final class IpRateLimitServiceTest extends TestCase
{
    private PDO $pdo;

    private IpRateLimitService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            <<<'SQL'
            CREATE TABLE ip_rate_limit (
                bucket_key TEXT PRIMARY KEY,
                window_start TEXT NOT NULL,
                request_count INTEGER NOT NULL
            )
            SQL
        );

        $this->service = new IpRateLimitService($this->pdo);
    }

    public function testCheckAndRecordAllowsRequestsWithinLimit(): void
    {
        for ($index = 0; $index < 60; $index++) {
            $this->assertTrue(
                $this->service->checkAndRecord('192.0.2.10', IpRateLimitBucket::QueuePoll)
            );
        }
    }

    public function testCheckAndRecordBlocksRequestsAboveLimit(): void
    {
        for ($index = 0; $index < 60; $index++) {
            $this->service->checkAndRecord('192.0.2.11', IpRateLimitBucket::QueuePoll);
        }

        $this->assertFalse(
            $this->service->checkAndRecord('192.0.2.11', IpRateLimitBucket::QueuePoll)
        );
    }

    public function testDifferentBucketsTrackSeparately(): void
    {
        for ($index = 0; $index < 30; $index++) {
            $this->service->checkAndRecord('192.0.2.12', IpRateLimitBucket::ScanLogPoll);
        }

        $this->assertFalse(
            $this->service->checkAndRecord('192.0.2.12', IpRateLimitBucket::ScanLogPoll)
        );
        $this->assertTrue(
            $this->service->checkAndRecord('192.0.2.12', IpRateLimitBucket::QueuePoll)
        );
    }

    public function testQueueSubmitBucketAllowsTenRequestsPerMinute(): void
    {
        for ($index = 0; $index < 10; $index++) {
            $this->assertTrue(
                $this->service->checkAndRecord('192.0.2.13', IpRateLimitBucket::QueueSubmit)
            );
        }

        $this->assertFalse(
            $this->service->checkAndRecord('192.0.2.13', IpRateLimitBucket::QueueSubmit)
        );
    }

    public function testQueueSubmitBucketTracksSeparatelyFromQueuePoll(): void
    {
        for ($index = 0; $index < 10; $index++) {
            $this->service->checkAndRecord('192.0.2.14', IpRateLimitBucket::QueueSubmit);
        }

        $this->assertFalse(
            $this->service->checkAndRecord('192.0.2.14', IpRateLimitBucket::QueueSubmit)
        );
        $this->assertTrue(
            $this->service->checkAndRecord('192.0.2.14', IpRateLimitBucket::QueuePoll)
        );
    }

    public function testEmptyIpSharesUnknownClientRateLimitBucket(): void
    {
        for ($index = 0; $index < 10; $index++) {
            $this->assertTrue(
                $this->service->checkAndRecord('', IpRateLimitBucket::QueueSubmit)
            );
        }

        $this->assertFalse(
            $this->service->checkAndRecord('', IpRateLimitBucket::QueueSubmit)
        );
    }

    public function testUnknownClientBucketIsSeparateFromKnownIp(): void
    {
        for ($index = 0; $index < 10; $index++) {
            $this->service->checkAndRecord('', IpRateLimitBucket::QueueSubmit);
        }

        $this->assertFalse(
            $this->service->checkAndRecord('', IpRateLimitBucket::QueueSubmit)
        );
        $this->assertTrue(
            $this->service->checkAndRecord('192.0.2.15', IpRateLimitBucket::QueueSubmit)
        );
    }

    public function testPlayerReportBucketAllowsFiveRequestsPerMinute(): void
    {
        for ($index = 0; $index < 5; $index++) {
            $this->assertTrue(
                $this->service->checkAndRecord('192.0.2.16', IpRateLimitBucket::PlayerReport)
            );
        }

        $this->assertFalse(
            $this->service->checkAndRecord('192.0.2.16', IpRateLimitBucket::PlayerReport)
        );
    }

    public function testPlayerReportBucketTracksSeparatelyFromQueueSubmit(): void
    {
        for ($index = 0; $index < 5; $index++) {
            $this->service->checkAndRecord('192.0.2.17', IpRateLimitBucket::PlayerReport);
        }

        $this->assertFalse(
            $this->service->checkAndRecord('192.0.2.17', IpRateLimitBucket::PlayerReport)
        );
        $this->assertTrue(
            $this->service->checkAndRecord('192.0.2.17', IpRateLimitBucket::QueueSubmit)
        );
    }
}
