<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerLogFilter.php';
require_once __DIR__ . '/PlayerLogPage.php';
require_once __DIR__ . '/PlayerLogService.php';
require_once __DIR__ . '/PlayerNavigation.php';
require_once __DIR__ . '/PlayerPlatformFilterOptions.php';
require_once __DIR__ . '/PlayerSummary.php';
require_once __DIR__ . '/PlayerSummaryService.php';
require_once __DIR__ . '/TrophyRarityFormatter.php';

final class PlayerLogPageContext
{
    private const STATUS_FLAGGED = 1;
    private const STATUS_PRIVATE = 3;

    private PlayerLogPage $playerLogPage;

    private PlayerSummary $playerSummary;

    private PlayerLogFilter $filter;

    private PlayerNavigation $playerNavigation;

    private PlayerPlatformFilterOptions $platformFilterOptions;

    private TrophyRarityFormatter $trophyRarityFormatter;

    private string $title;

    private string $playerOnlineId;

    private int $playerAccountId;

    private int $playerStatus;

    /**
     * @param array<string, mixed> $playerData
     * @param array<string, mixed> $queryParameters
     */
    public static function fromGlobals(
        PDO $database,
        array $playerData,
        int $accountId,
        array $queryParameters
    ): self {
        $filter = PlayerLogFilter::fromArray($queryParameters);
        $playerLogService = new PlayerLogService($database);
        $playerLogPage = new PlayerLogPage(
            $playerLogService,
            $filter,
            self::extractPlayerAccountId($playerData),
            self::extractPlayerStatus($playerData)
        );

        $playerSummaryService = new PlayerSummaryService($database);
        $playerSummary = $playerSummaryService->getSummary($accountId);

        return new self(
            $playerLogPage,
            $playerSummary,
            $filter,
            self::extractOnlineId($playerData),
            self::extractPlayerAccountId($playerData),
            self::extractPlayerStatus($playerData)
        );
    }

    public static function fromComponents(
        PlayerLogPage $playerLogPage,
        PlayerSummary $playerSummary,
        PlayerLogFilter $filter,
        string $playerOnlineId,
        int $playerAccountId,
        int $playerStatus
    ): self {
        return new self(
            $playerLogPage,
            $playerSummary,
            $filter,
            $playerOnlineId,
            $playerAccountId,
            $playerStatus
        );
    }

    private function __construct(
        PlayerLogPage $playerLogPage,
        PlayerSummary $playerSummary,
        PlayerLogFilter $filter,
        string $playerOnlineId,
        int $playerAccountId,
        int $playerStatus
    ) {
        $this->playerLogPage = $playerLogPage;
        $this->playerSummary = $playerSummary;
        $this->filter = $filter;
        $this->playerOnlineId = $playerOnlineId;
        $this->playerAccountId = $playerAccountId;
        $this->playerStatus = $playerStatus;
        $this->playerNavigation = PlayerNavigation::forSection($playerOnlineId, PlayerNavigation::SECTION_LOG);
        $this->platformFilterOptions = PlayerPlatformFilterOptions::fromSelectionCallback(
            fn (string $platform): bool => $this->filter->isPlatformSelected($platform)
        );
        $this->trophyRarityFormatter = new TrophyRarityFormatter();
        $this->title = sprintf("%s's Trophy Log ~ PSN 100%%", $playerOnlineId);
    }

    public function getPlayerLogPage(): PlayerLogPage
    {
        return $this->playerLogPage;
    }

    public function getPlayerSummary(): PlayerSummary
    {
        return $this->playerSummary;
    }

    public function getFilter(): PlayerLogFilter
    {
        return $this->filter;
    }

    public function getPlayerNavigation(): PlayerNavigation
    {
        return $this->playerNavigation;
    }

    public function getPlatformFilterOptions(): PlayerPlatformFilterOptions
    {
        return $this->platformFilterOptions;
    }

    public function getTrophyRarityFormatter(): TrophyRarityFormatter
    {
        return $this->trophyRarityFormatter;
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
        return $this->playerStatus === self::STATUS_FLAGGED;
    }

    public function isPlayerPrivate(): bool
    {
        return $this->playerStatus === self::STATUS_PRIVATE;
    }

    public function shouldDisplayLog(): bool
    {
        return !$this->isPlayerFlagged() && !$this->isPlayerPrivate();
    }

    /**
     * @return PlayerLogEntry[]
     */
    public function getTrophies(): array
    {
        return $this->playerLogPage->getTrophies();
    }

    private static function extractOnlineId(array $playerData): string
    {
        return (string) ($playerData['online_id'] ?? '');
    }

    private static function extractPlayerAccountId(array $playerData): int
    {
        return (int) ($playerData['account_id'] ?? 0);
    }

    private static function extractPlayerStatus(array $playerData): int
    {
        return (int) ($playerData['status'] ?? 0);
    }
}
