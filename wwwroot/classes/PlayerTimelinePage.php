<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerTimelineService.php';
require_once __DIR__ . '/PlayerSummary.php';
require_once __DIR__ . '/PlayerSummaryService.php';
require_once __DIR__ . '/PlayerStatus.php';

class PlayerTimelinePage
{
    private PlayerSummary $playerSummary;

    // /**
    //  * @var PlayerTimeline[]
    //  */
    // private array $timelines;

    private PlayerStatus $playerStatus;

    public function __construct(
        PlayerTimelineService $timelineService,
        PlayerSummaryService $summaryService,
        int $accountId,
        PlayerStatus $playerStatus
    ) {
        $this->playerSummary = $summaryService->getSummary($accountId);
        $this->playerStatus = $playerStatus;

        if ($this->shouldLoadTimeline()) {
            $this->timelines = $timelineService->getTimelines($accountId);
        } else {
            $this->timelines = [];
        }
    }

    public function getPlayerSummary(): PlayerSummary
    {
        return $this->playerSummary;
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
        return false;
        //return $this->shouldShowTimeline();
    }
}
