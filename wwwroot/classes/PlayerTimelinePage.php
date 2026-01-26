<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerTimelineData.php';
require_once __DIR__ . '/PlayerTimelineService.php';
require_once __DIR__ . '/PlayerSummary.php';
require_once __DIR__ . '/PlayerSummaryService.php';
require_once __DIR__ . '/PlayerStatus.php';

class PlayerTimelinePage
{
    private PlayerSummary $playerSummary;

    private PlayerStatus $playerStatus;

    private ?PlayerTimelineData $timelineData = null;

    public function __construct(
        PlayerTimelineService $timelineService,
        PlayerSummaryService $summaryService,
        int $accountId,
        PlayerStatus $playerStatus
    ) {
        $this->playerSummary = $summaryService->getSummary($accountId);
        $this->playerStatus = $playerStatus;

        if ($this->shouldLoadTimeline()) {
            $this->timelineData = $timelineService->getTimelineData($accountId);
        }
    }

    public function getPlayerSummary(): PlayerSummary
    {
        return $this->playerSummary;
    }

    public function getTimelineData(): ?PlayerTimelineData
    {
        return $this->timelineData;
    }

    // /**
    //  * @return PlayerTimeline[]
    //  */
    // public function getTimeline(): array
    // {
    //     return $this->timelines;
    // }

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
