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
                $this->service->checkAndRecord('192.0.2.10', IpRateLimitService::BUCKET_QUEUE_POLL)
            );
        }
    }

    public function testCheckAndRecordBlocksRequestsAboveLimit(): void
    {
        for ($index = 0; $index < 60; $index++) {
            $this->service->checkAndRecord('192.0.2.11', IpRateLimitService::BUCKET_QUEUE_POLL);
        }

        $this->assertFalse(
            $this->service->checkAndRecord('192.0.2.11', IpRateLimitService::BUCKET_QUEUE_POLL)
        );
    }

    public function testDifferentBucketsTrackSeparately(): void
    {
        for ($index = 0; $index < 30; $index++) {
            $this->service->checkAndRecord('192.0.2.12', IpRateLimitService::BUCKET_SCAN_LOG_POLL);
        }

        $this->assertFalse(
            $this->service->checkAndRecord('192.0.2.12', IpRateLimitService::BUCKET_SCAN_LOG_POLL)
        );
        $this->assertTrue(
            $this->service->checkAndRecord('192.0.2.12', IpRateLimitService::BUCKET_QUEUE_POLL)
        );
    }

    public function testQueueSubmitBucketAllowsTenRequestsPerMinute(): void
    {
        for ($index = 0; $index < 10; $index++) {
            $this->assertTrue(
                $this->service->checkAndRecord('192.0.2.13', IpRateLimitService::BUCKET_QUEUE_SUBMIT)
            );
        }

        $this->assertFalse(
            $this->service->checkAndRecord('192.0.2.13', IpRateLimitService::BUCKET_QUEUE_SUBMIT)
        );
    }

    public function testQueueSubmitBucketTracksSeparatelyFromQueuePoll(): void
    {
        for ($index = 0; $index < 10; $index++) {
            $this->service->checkAndRecord('192.0.2.14', IpRateLimitService::BUCKET_QUEUE_SUBMIT);
        }

        $this->assertFalse(
            $this->service->checkAndRecord('192.0.2.14', IpRateLimitService::BUCKET_QUEUE_SUBMIT)
        );
        $this->assertTrue(
            $this->service->checkAndRecord('192.0.2.14', IpRateLimitService::BUCKET_QUEUE_POLL)
        );
    }

    public function testEmptyIpSharesUnknownClientRateLimitBucket(): void
    {
        for ($index = 0; $index < 10; $index++) {
            $this->assertTrue(
                $this->service->checkAndRecord('', IpRateLimitService::BUCKET_QUEUE_SUBMIT)
            );
        }

        $this->assertFalse(
            $this->service->checkAndRecord('', IpRateLimitService::BUCKET_QUEUE_SUBMIT)
        );
    }

    public function testUnknownClientBucketIsSeparateFromKnownIp(): void
    {
        for ($index = 0; $index < 10; $index++) {
            $this->service->checkAndRecord('', IpRateLimitService::BUCKET_QUEUE_SUBMIT);
        }

        $this->assertFalse(
            $this->service->checkAndRecord('', IpRateLimitService::BUCKET_QUEUE_SUBMIT)
        );
        $this->assertTrue(
            $this->service->checkAndRecord('192.0.2.15', IpRateLimitService::BUCKET_QUEUE_SUBMIT)
        );
    }

    public function testPlayerReportBucketAllowsFiveRequestsPerMinute(): void
    {
        for ($index = 0; $index < 5; $index++) {
            $this->assertTrue(
                $this->service->checkAndRecord('192.0.2.16', IpRateLimitService::BUCKET_PLAYER_REPORT)
            );
        }

        $this->assertFalse(
            $this->service->checkAndRecord('192.0.2.16', IpRateLimitService::BUCKET_PLAYER_REPORT)
        );
    }

    public function testPlayerReportBucketTracksSeparatelyFromQueueSubmit(): void
    {
        for ($index = 0; $index < 5; $index++) {
            $this->service->checkAndRecord('192.0.2.17', IpRateLimitService::BUCKET_PLAYER_REPORT);
        }

        $this->assertFalse(
            $this->service->checkAndRecord('192.0.2.17', IpRateLimitService::BUCKET_PLAYER_REPORT)
        );
        $this->assertTrue(
            $this->service->checkAndRecord('192.0.2.17', IpRateLimitService::BUCKET_QUEUE_SUBMIT)
        );
    }
}
