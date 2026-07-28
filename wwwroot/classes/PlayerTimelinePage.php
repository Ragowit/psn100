<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerTimelineData.php';
require_once __DIR__ . '/PlayerTimelineService.php';
require_once __DIR__ . '/PlayerSummary.php';
require_once __DIR__ . '/PlayerSummaryService.php';
require_once __DIR__ . '/PlayerStatus.php';

final readonly class PlayerTimelinePage
{
    private PlayerSummary $playerSummary;

    private ?PlayerTimelineData $timelineData;

    public function __construct(
        PlayerTimelineService $timelineService,
        PlayerSummaryService $summaryService,
        int $accountId,
        private PlayerStatus $playerStatus,
    ) {
        $this->playerSummary = $summaryService->getSummary($accountId);
        $this->timelineData = $this->shouldLoadTimeline()
            ? $timelineService->getTimelineData($accountId)
            : null;
    }

    public function getPlayerSummary(): PlayerSummary
    {
        return $this->playerSummary;
    }

    public function getTimelineData(): ?PlayerTimelineData
    {
        return $this->timelineData;
    }

    public function shouldShowFlaggedMessage(): bool
    {
        return $this->playerStatus->isFlagged();
    }

    public function shouldShowPrivateMessage(): bool
    {
        return $this->playerStatus->isPrivateProfile();
    }

    public function shouldShowTimeline(): bool
    {
        return !$this->shouldShowFlaggedMessage() && !$this->shouldShowPrivateMessage();
    }

    private function shouldLoadTimeline(): bool
    {
        return $this->shouldShowTimeline();
    }
}
