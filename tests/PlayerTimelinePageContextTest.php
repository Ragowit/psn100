<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerTimelinePageContext.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerTimelineData.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerTimelineEntry.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerTimelineService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerSummary.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerSummaryService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerStatus.php';

final class PlayerTimelinePageContextTest extends TestCase
{
    public function testContextAggregatesDependencies(): void
    {
        $summary = new PlayerSummary(8, 2, 55.5, 30);
        $timelineData = $this->createTimelineData();
        $page = $this->createPage($summary, $timelineData, PlayerStatus::NORMAL);

        $context = PlayerTimelinePageContext::fromComponents(
            $page,
            $summary,
            'ExampleUser',
            123,
            PlayerStatus::NORMAL
        );

        $this->assertSame($page, $context->getPlayerTimelinePage());
        $this->assertSame($summary, $context->getPlayerSummary());
        $this->assertSame("ExampleUser's Timeline ~ PSN 100%", $context->getTitle());
        $this->assertSame('ExampleUser', $context->getPlayerOnlineId());
        $this->assertSame(123, $context->getPlayerAccountId());
        $this->assertTrue($context->shouldShowTimeline());
        $this->assertFalse($context->isPlayerFlagged());
        $this->assertFalse($context->isPlayerPrivate());
        $this->assertSame($timelineData, $context->getTimelineData());

        $links = $context->getPlayerNavigation()->getLinks();
        $timelineLink = null;

        foreach ($links as $link) {
            if ($link->getLabel() === 'Timeline') {
                $timelineLink = $link;
                break;
            }
        }

        $this->assertTrue($timelineLink instanceof PlayerNavigationLink);
        $this->assertTrue($timelineLink->isActive());
        $this->assertSame('/player/ExampleUser/timeline', $timelineLink->getUrl());
    }

    public function testContextReflectsPlayerStatuses(): void
    {
        $summary = new PlayerSummary(0, 0, null, 0);
        $timelineData = $this->createTimelineData();

        $flaggedPage = $this->createPage($summary, $timelineData, PlayerStatus::FLAGGED);
        $flaggedContext = PlayerTimelinePageContext::fromComponents(
            $flaggedPage,
            $summary,
            'FlaggedUser',
            99,
            PlayerStatus::FLAGGED
        );

        $this->assertTrue($flaggedContext->isPlayerFlagged());
        $this->assertTrue($flaggedContext->shouldShowFlaggedMessage());
        $this->assertFalse($flaggedContext->shouldShowTimeline());
        $this->assertSame(null, $flaggedContext->getTimelineData());

        $privatePage = $this->createPage($summary, $timelineData, PlayerStatus::PRIVATE_PROFILE);
        $privateContext = PlayerTimelinePageContext::fromComponents(
            $privatePage,
            $summary,
            'PrivateUser',
            88,
            PlayerStatus::PRIVATE_PROFILE
        );

        $this->assertTrue($privateContext->isPlayerPrivate());
        $this->assertTrue($privateContext->shouldShowPrivateMessage());
        $this->assertFalse($privateContext->shouldShowTimeline());
        $this->assertSame(null, $privateContext->getTimelineData());
    }

    private function createTimelineData(): PlayerTimelineData
    {
        $entry = PlayerTimelineEntry::fromRow([
            'game_id' => 12,
            'name' => 'Timeline Game',
            'progress' => 15,
            'first_trophy' => '2024-02-01',
            'last_trophy' => '2024-03-10',
        ]);

        return new PlayerTimelineData(
            new DateTimeImmutable('2024-02-01'),
            new DateTimeImmutable('2024-04-01'),
            [$entry]
        );
    }

    private function createPage(
        PlayerSummary $summary,
        ?PlayerTimelineData $timelineData,
        PlayerStatus $status
    ): PlayerTimelinePage {
        $summaryService = new class($summary) extends PlayerSummaryService {
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

        $timelineService = new class($timelineData) extends PlayerTimelineService {
            private ?PlayerTimelineData $timelineData;

            public function __construct(?PlayerTimelineData $timelineData)
            {
                $this->timelineData = $timelineData;
            }

            public function getTimelineData(int $accountId): ?PlayerTimelineData
            {
                return $this->timelineData;
            }
        };

        return new PlayerTimelinePage($timelineService, $summaryService, 42, $status);
    }
}
