<?php

declare(strict_types=1);

//require_once __DIR__ . '/PlayerTimeline.php';
require_once __DIR__ . '/PlayerTimelineData.php';
require_once __DIR__ . '/PlayerTimelinePage.php';
require_once __DIR__ . '/PlayerTimelineService.php';
require_once __DIR__ . '/PlayerSummary.php';
require_once __DIR__ . '/PlayerSummaryService.php';
require_once __DIR__ . '/PlayerNavigation.php';
require_once __DIR__ . '/PlayerNavigationSection.php';
require_once __DIR__ . '/Utility.php';
require_once __DIR__ . '/PlayerStatus.php';

final class PlayerTimelinePageContext
{
    private PlayerTimelinePage $playerTimelinePage;

    private PlayerSummary $playerSummary;

    private PlayerNavigation $playerNavigation;

    private string $title;

    private string $playerOnlineId;

    private int $playerAccountId;

    private PlayerStatus $playerStatus;

    /**
     * @param array<string, mixed> $playerData
     * @param array<string, mixed> $queryParameters
     */
    public static function fromGlobals(
        PDO $database,
        Utility $utility,
        array $playerData,
        int $accountId,
        array $queryParameters
    ): self {
        $playerStatus = self::extractPlayerStatus($playerData);

        $timelineService = new PlayerTimelineService($database);
        $summaryService = new PlayerSummaryService($database);

        $playerTimelinePage = new PlayerTimelinePage(
            $timelineService,
            $summaryService,
            $accountId,
            $playerStatus
        );

        return self::fromComponents(
            $playerTimelinePage,
            $playerTimelinePage->getPlayerSummary(),
            self::extractOnlineId($playerData),
            $accountId,
            $playerStatus
        );
    }

    public static function fromComponents(
        PlayerTimelinePage $playerTimelinePage,
        PlayerSummary $playerSummary,
        string $playerOnlineId,
        int $playerAccountId,
        PlayerStatus $playerStatus
    ): self {
        return new self(
            $playerTimelinePage,
            $playerSummary,
            $playerOnlineId,
            $playerAccountId,
            $playerStatus
        );
    }

    private function __construct(
        PlayerTimelinePage $playerTimelinePage,
        PlayerSummary $playerSummary,
        string $playerOnlineId,
        int $playerAccountId,
        PlayerStatus $playerStatus
    ) {
        $this->playerTimelinePage = $playerTimelinePage;
        $this->playerSummary = $playerSummary;
        $this->playerOnlineId = $playerOnlineId;
        $this->playerAccountId = $playerAccountId;
        $this->playerStatus = $playerStatus;
        $this->playerNavigation = PlayerNavigation::forSection($playerOnlineId, PlayerNavigationSection::TIMELINE);
        $this->title = sprintf("%s's Timeline ~ PSN 100%%", $playerOnlineId);
    }

    public function getPlayerTimelinePage(): PlayerTimelinePage
    {
        return $this->playerTimelinePage;
    }

    public function getPlayerSummary(): PlayerSummary
    {
        return $this->playerSummary;
    }

    public function getPlayerNavigation(): PlayerNavigation
    {
        return $this->playerNavigation;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPlayerOnlineId(): string
    {
        return $this->playerOnlineId;
    }

    public function getPlayerAccountId(): int
    {
        return $this->playerAccountId;
    }

    public function isPlayerFlagged(): bool
    {
        return $this->playerStatus->isFlagged();
    }

    public function isPlayerPrivate(): bool
    {
        return $this->playerStatus->isPrivateProfile();
    }

    public function shouldShowFlaggedMessage(): bool
    {
        return $this->playerTimelinePage->shouldShowFlaggedMessage();
    }

    public function shouldShowPrivateMessage(): bool
    {
        return $this->playerTimelinePage->shouldShowPrivateMessage();
    }

    public function shouldShowTimeline(): bool
    {
        return $this->playerTimelinePage->shouldShowTimeline();
    }

    public function getTimelineData(): ?PlayerTimelineData
    {
        return $this->playerTimelinePage->getTimelineData();
    }

    // /**
    //  * @return PlayerTimeline[]
    //  */
    // public function getTimeline(): array
    // {
    //     return $this->playerTimelinePage->getTimeline();
    // }

    /**
     * @param array<string, mixed> $playerData
     */
    private static function extractOnlineId(array $playerData): string
    {
        return (string) ($playerData['online_id'] ?? '');
    }

    /**
     * @param array<string, mixed> $playerData
     */
    private static function extractPlayerStatus(array $playerData): PlayerStatus
    {
        return PlayerStatus::fromValue((int) ($playerData['status'] ?? 0));
    }
}
