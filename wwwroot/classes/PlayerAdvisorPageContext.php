<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerAdvisorFilter.php';
require_once __DIR__ . '/PlayerAdvisorPage.php';
require_once __DIR__ . '/PlayerAdvisorService.php';
require_once __DIR__ . '/PlayerNavigation.php';
require_once __DIR__ . '/PlayerPlatformFilterOptions.php';
require_once __DIR__ . '/PlayerStatusNotice.php';
require_once __DIR__ . '/PlayerSummary.php';
require_once __DIR__ . '/PlayerSummaryService.php';
require_once __DIR__ . '/TrophyRarityFormatter.php';
require_once __DIR__ . '/Utility.php';

final class PlayerAdvisorPageContext
{
    private const STATUS_FLAGGED = 1;
    private const STATUS_PRIVATE = 3;

    private PlayerAdvisorPage $playerAdvisorPage;

    private PlayerSummary $playerSummary;

    private PlayerAdvisorFilter $filter;

    private PlayerNavigation $playerNavigation;

    private PlayerPlatformFilterOptions $platformFilterOptions;

    private TrophyRarityFormatter $trophyRarityFormatter;

    private ?PlayerStatusNotice $playerStatusNotice;

    private string $playerOnlineId;

    private int $playerAccountId;

    private int $playerStatus;

    private ?string $playerAccountIdValue;

    private string $title;

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
        $filter = PlayerAdvisorFilter::fromArray($queryParameters);
        $playerStatus = self::extractPlayerStatus($playerData);
        $playerOnlineId = self::extractPlayerOnlineId($playerData);
        $playerAccountIdValue = self::extractPlayerAccountId($playerData);

        $playerAdvisorService = new PlayerAdvisorService($database, $utility);
        $playerSummaryService = new PlayerSummaryService($database);

        $playerAdvisorPage = new PlayerAdvisorPage(
            $playerAdvisorService,
            $playerSummaryService,
            $filter,
            $accountId,
            $playerStatus
        );

        return self::fromComponents(
            $playerAdvisorPage,
            $filter,
            $playerOnlineId,
            $accountId,
            $playerStatus,
            $playerAccountIdValue
        );
    }

    public static function fromComponents(
        PlayerAdvisorPage $playerAdvisorPage,
        PlayerAdvisorFilter $filter,
        string $playerOnlineId,
        int $playerAccountId,
        int $playerStatus,
        ?string $playerAccountIdValue = null
    ): self {
        return new self(
            $playerAdvisorPage,
            $playerAdvisorPage->getPlayerSummary(),
            $filter,
            $playerOnlineId,
            $playerAccountId,
            $playerStatus,
            $playerAccountIdValue
        );
    }

    private function __construct(
        PlayerAdvisorPage $playerAdvisorPage,
        PlayerSummary $playerSummary,
        PlayerAdvisorFilter $filter,
        string $playerOnlineId,
        int $playerAccountId,
        int $playerStatus,
        ?string $playerAccountIdValue
    ) {
        $this->playerAdvisorPage = $playerAdvisorPage;
        $this->playerSummary = $playerSummary;
        $this->filter = $filter;
        $this->playerOnlineId = $playerOnlineId;
        $this->playerAccountId = $playerAccountId;
        $this->playerStatus = $playerStatus;
        $this->playerAccountIdValue = $playerAccountIdValue;
        $this->playerNavigation = PlayerNavigation::forSection(
            $playerOnlineId,
            PlayerNavigation::SECTION_TROPHY_ADVISOR
        );
        $this->platformFilterOptions = PlayerPlatformFilterOptions::fromSelectionCallback(
            fn (string $platform): bool => $this->filter->isPlatformSelected($platform)
        );
        $this->trophyRarityFormatter = new TrophyRarityFormatter();
        $this->title = sprintf("%s's Trophy Advisor ~ PSN 100%%", $playerOnlineId);
        $this->playerStatusNotice = $this->createPlayerStatusNotice();
    }

    public function getPlayerAdvisorPage(): PlayerAdvisorPage
    {
        return $this->playerAdvisorPage;
    }

    public function getPlayerSummary(): PlayerSummary
    {
        return $this->playerSummary;
    }

    public function getFilter(): PlayerAdvisorFilter
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

    public function getPlayerStatusNotice(): ?PlayerStatusNotice
    {
        return $this->playerStatusNotice;
    }

    public function getPlayerOnlineId(): string
    {
        return $this->playerOnlineId;
    }

    public function getPlayerAccountId(): int
    {
        return $this->playerAccountId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function shouldDisplayAdvisor(): bool
    {
        return $this->playerAdvisorPage->shouldDisplayAdvisor();
    }

    private function createPlayerStatusNotice(): ?PlayerStatusNotice
    {
        if ($this->playerAdvisorPage->shouldDisplayAdvisor()) {
            return null;
        }

        return match ($this->playerStatus) {
            self::STATUS_FLAGGED => PlayerStatusNotice::flagged(
                $this->playerOnlineId,
                $this->playerAccountIdValue
            ),
            self::STATUS_PRIVATE => PlayerStatusNotice::privateProfile(),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $playerData
     */
    private static function extractPlayerOnlineId(array $playerData): string
    {
        return (string) ($playerData['online_id'] ?? '');
    }

    /**
     * @param array<string, mixed> $playerData
     */
    private static function extractPlayerStatus(array $playerData): int
    {
        return (int) ($playerData['status'] ?? 0);
    }

    /**
     * @param array<string, mixed> $playerData
     */
    private static function extractPlayerAccountId(array $playerData): ?string
    {
        if (!array_key_exists('account_id', $playerData)) {
            return null;
        }

        $value = $playerData['account_id'];

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
