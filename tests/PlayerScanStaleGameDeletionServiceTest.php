<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanStaleGameDeletionService.php';

final class PlayerScanStaleGameDeletionServiceTest extends TestCase
{
    private PlayerScanStaleGameDeletionService $service;

    protected function setUp(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->service = new PlayerScanStaleGameDeletionService($database);
    }

    public function testDeletionIsSkippedWhenSonyReturnsNoGames(): void
    {
        $shouldDelete = $this->service->shouldDeleteMissingZeroPercentGames(0, 120, []);

        $this->assertFalse($shouldDelete);
    }

    public function testDeletionProceedsWhenCountsDifferAndSonyReturnedGames(): void
    {
        $shouldDelete = $this->service->shouldDeleteMissingZeroPercentGames(3, 5, ['N0001', 'N0002', 'N0003']);

        $this->assertTrue($shouldDelete);
    }

    public function testDeletionSkippedWhenCountsMatch(): void
    {
        $shouldDelete = $this->service->shouldDeleteMissingZeroPercentGames(4, 4, ['N0001', 'N0002', 'N0003', 'N0004']);

        $this->assertFalse($shouldDelete);
    }

    public function testSuppressesDeletionWhenScanDidNotCompleteCleanlyAndDeltaIsLarge(): void
    {
        $shouldSuppress = $this->service->shouldSuppressDeletionForIncompleteScan(true, -50, false);

        $this->assertTrue($shouldSuppress);
    }

    public function testDoesNotSuppressDeletionWhenScanCompletedCleanly(): void
    {
        $shouldSuppress = $this->service->shouldSuppressDeletionForIncompleteScan(true, -50, true);

        $this->assertFalse($shouldSuppress);
    }

    public function testRetriesWhenSonyReturnsNoGamesButLocalGamesExist(): void
    {
        $this->assertTrue($this->service->shouldRetryWhenSonyReturnsNoGames(0, 5));
        $this->assertFalse($this->service->shouldRetryWhenSonyReturnsNoGames(3, 5));
        $this->assertFalse($this->service->shouldRetryWhenSonyReturnsNoGames(0, 0));
    }
}
