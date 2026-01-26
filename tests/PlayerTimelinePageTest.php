<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerTimelinePage.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerTimelineData.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerTimelineEntry.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerTimelineService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerSummary.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerSummaryService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerStatus.php';

final class PlayerTimelinePageTest extends TestCase
{
    public function testPageLoadsTimelineForPublicPlayers(): void
    {
        $summary = new PlayerSummary(12, 4, 66.6, 20);
        $timelineData = $this->createTimelineData();
        $summaryService = $this->createSummaryService($summary);
        $timelineService = $this->createTimelineService($timelineData);

        $page = new PlayerTimelinePage($timelineService, $summaryService, 321, PlayerStatus::NORMAL);

        $this->assertSame($summary, $page->getPlayerSummary());
        $this->assertSame($timelineData, $page->getTimelineData());
        $this->assertTrue($page->shouldShowTimeline());
        $this->assertFalse($page->shouldShowFlaggedMessage());
        $this->assertFalse($page->shouldShowPrivateMessage());
        $this->assertSame(1, $timelineService->getCallCount());
    }

    public function testPageSkipsTimelineForFlaggedAndPrivatePlayers(): void
    {
        $summary = new PlayerSummary(0, 0, null, 0);
        $timelineData = $this->createTimelineData();

        $flaggedService = $this->createTimelineService($timelineData);
        $flaggedPage = new PlayerTimelinePage(
            $flaggedService,
            $this->createSummaryService($summary),
            10,
            PlayerStatus::FLAGGED
        );

        $this->assertTrue($flaggedPage->shouldShowFlaggedMessage());
        $this->assertFalse($flaggedPage->shouldShowPrivateMessage());
        $this->assertFalse($flaggedPage->shouldShowTimeline());
        $this->assertSame(null, $flaggedPage->getTimelineData());
        $this->assertSame(0, $flaggedService->getCallCount());

        $privateService = $this->createTimelineService($timelineData);
        $privatePage = new PlayerTimelinePage(
            $privateService,
            $this->createSummaryService($summary),
            11,
            PlayerStatus::PRIVATE_PROFILE
        );

        $this->assertFalse($privatePage->shouldShowFlaggedMessage());
        $this->assertTrue($privatePage->shouldShowPrivateMessage());
        $this->assertFalse($privatePage->shouldShowTimeline());
        $this->assertSame(null, $privatePage->getTimelineData());
        $this->assertSame(0, $privateService->getCallCount());
    }

    private function createTimelineData(): PlayerTimelineData
    {
        $entry = PlayerTimelineEntry::fromRow([
            'game_id' => 50,
            'name' => 'Example Game',
            'progress' => 42,
            'first_trophy' => '2024-01-01',
            'last_trophy' => '2024-02-01',
        ]);

        return new PlayerTimelineData(
            new DateTimeImmutable('2024-01-01'),
            new DateTimeImmutable('2024-03-01'),
            [$entry]
        );
    }

    private function createSummaryService(PlayerSummary $summary): PlayerSummaryService
    {
        return new class($summary) extends PlayerSummaryService {
            private PlayerSummary $summary;

            public function __construct(PlayerSummary $summary)
            {
                $this->summary = $summary;
            }

            public function getSummary(int $accountId): PlayerSummary
            {
                return $this->summary;
            }
        };
    }

    private function createTimelineService(?PlayerTimelineData $timelineData): PlayerTimelineService
    {
        return new class($timelineData) extends PlayerTimelineService {
            private ?PlayerTimelineData $timelineData;

            private int $callCount = 0;

            public function __construct(?PlayerTimelineData $timelineData)
            {
                $this->timelineData = $timelineData;
            }

            public function getTimelineData(int $accountId): ?PlayerTimelineData
            {
                $this->callCount++;

                return $this->timelineData;
            }

            public function getCallCount(): int
            {
                return $this->callCount;
            }
        };
    }
}
