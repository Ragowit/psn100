<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerRandomGame.php';
require_once __DIR__ . '/PlayerRandomGamesFilter.php';
require_once __DIR__ . '/PlayerRandomGamesPage.php';
require_once __DIR__ . '/PlayerRandomGamesService.php';
require_once __DIR__ . '/PlayerSummary.php';
require_once __DIR__ . '/PlayerSummaryService.php';
require_once __DIR__ . '/PlayerNavigation.php';
require_once __DIR__ . '/PlayerPlatformFilterOptions.php';
require_once __DIR__ . '/Utility.php';
require_once __DIR__ . '/PlayerStatus.php';

final class PlayerRandomGamesPageContext
{
    private const int STATUS_FLAGGED = 1;
    private const int STATUS_PRIVATE = 3;

    private PlayerRandomGamesPage $playerRandomGamesPage;

    private PlayerSummary $playerSummary;

    private PlayerRandomGamesFilter $filter;

    private PlayerNavigation $playerNavigation;

    private PlayerPlatformFilterOptions $platformFilterOptions;

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
        $filter = PlayerRandomGamesFilter::fromArray($queryParameters);
        $playerStatus = self::extractPlayerStatus($playerData);

        $randomGamesService = new PlayerRandomGamesService($database, $utility);
        $summaryService = new PlayerSummaryService($database);

        $playerRandomGamesPage = new PlayerRandomGamesPage(
            $randomGamesService,
            $summaryService,
            $filter,
            $accountId,
            $playerStatus
        );

        return self::fromComponents(
            $playerRandomGamesPage,
            $playerRandomGamesPage->getPlayerSummary(),
            $playerRandomGamesPage->getFilter(),
            self::extractOnlineId($playerData),
            $accountId,
            $playerStatus
        );
    }

    public static function fromComponents(
        PlayerRandomGamesPage $playerRandomGamesPage,
        PlayerSummary $playerSummary,
        PlayerRandomGamesFilter $filter,
        string $playerOnlineId,
        int $playerAccountId,
        PlayerStatus $playerStatus
    ): self {
        return new self(
            $playerRandomGamesPage,
            $playerSummary,
            $filter,
            $playerOnlineId,
            $playerAccountId,
            $playerStatus
        );
    }

    private function __construct(
        PlayerRandomGamesPage $playerRandomGamesPage,
        PlayerSummary $playerSummary,
        PlayerRandomGamesFilter $filter,
        string $playerOnlineId,
        int $playerAccountId,
        PlayerStatus $playerStatus
    ) {
        $this->playerRandomGamesPage = $playerRandomGamesPage;
        $this->playerSummary = $playerSummary;
        $this->filter = $filter;
        $this->playerOnlineId = $playerOnlineId;
        $this->playerAccountId = $playerAccountId;
        $this->playerStatus = $playerStatus;
        $this->playerNavigation = PlayerNavigation::forSection($playerOnlineId, PlayerNavigation::SECTION_RANDOM);
        $this->platformFilterOptions = PlayerPlatformFilterOptions::fromSelectionCallback(
            fn (string $platform): bool => $this->filter->isPlatformSelected($platform)
        );
        $this->title = sprintf("%s's Random Games ~ PSN 100%%", $playerOnlineId);
    }

    public function getPlayerRandomGamesPage(): PlayerRandomGamesPage
    {
        return $this->playerRandomGamesPage;
    }

    public function getPlayerSummary(): PlayerSummary
    {
        return $this->playerSummary;
    }

    public function getFilter(): PlayerRandomGamesFilter
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
        return $this->playerRandomGamesPage->shouldShowFlaggedMessage();
    }

    public function shouldShowPrivateMessage(): bool
    {
        return $this->playerRandomGamesPage->shouldShowPrivateMessage();
    }

    public function shouldShowRandomGames(): bool
    {
        return $this->playerRandomGamesPage->shouldShowRandomGames();
    }

    /**
     * @return PlayerRandomGame[]
     */
    public function getRandomGames(): array
    {
        return $this->playerRandomGamesPage->getRandomGames();
    }

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
