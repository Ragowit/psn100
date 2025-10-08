<?php

declare(strict_types=1);

require_once __DIR__ . '/../PlayerLeaderboardFilter.php';
require_once __DIR__ . '/../PlayerRarityLeaderboardService.php';
require_once __DIR__ . '/../PlayerLeaderboardPage.php';
require_once __DIR__ . '/../Utility.php';
require_once __DIR__ . '/RarityLeaderboardRow.php';

class RarityLeaderboardPageContext
{
    private const TITLE = 'PSN Rarity Leaderboard ~ PSN 100%';

    private PlayerLeaderboardPage $leaderboardPage;

    private PlayerLeaderboardFilter $filter;

    private Utility $utility;

    /**
     * @var array<string, string>
     */
    private array $filterParameters;

    /**
     * @var array<string, string>
     */
    private array $currentPageParameters;

    private ?string $highlightedPlayerId;

    /**
     * @var RarityLeaderboardRow[]
     */
    private array $rows;

    /**
     * @param array<string, mixed> $queryParameters
     */
    private function __construct(
        PlayerLeaderboardPage $leaderboardPage,
        PlayerLeaderboardFilter $filter,
        Utility $utility,
        array $queryParameters
    ) {
        $this->leaderboardPage = $leaderboardPage;
        $this->filter = $filter;
        $this->utility = $utility;
        $this->filterParameters = $leaderboardPage->getFilterParameters();
        $this->currentPageParameters = $leaderboardPage->getPageQueryParameters($leaderboardPage->getCurrentPage());
        $this->highlightedPlayerId = $this->resolveHighlightedPlayerId($queryParameters);
        $this->rows = $this->buildRows();
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function fromGlobals(PDO $database, Utility $utility, array $queryParameters): self
    {
        $playerLeaderboardService = new PlayerRarityLeaderboardService($database);
        $playerLeaderboardFilter = PlayerLeaderboardFilter::fromArray($queryParameters);
        $playerLeaderboardPage = new PlayerLeaderboardPage($playerLeaderboardService, $playerLeaderboardFilter);

        return new self($playerLeaderboardPage, $playerLeaderboardFilter, $utility, $queryParameters);
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    private function resolveHighlightedPlayerId(array $queryParameters): ?string
    {
        $highlightedPlayer = $queryParameters['player'] ?? null;

        if ($highlightedPlayer === null) {
            return null;
        }

        $highlightedPlayer = trim((string) $highlightedPlayer);

        return $highlightedPlayer !== '' ? $highlightedPlayer : null;
    }

    /**
     * @return RarityLeaderboardRow[]
     */
    private function buildRows(): array
    {
        $players = $this->leaderboardPage->getPlayers();

        return array_map(
            fn(array $player): RarityLeaderboardRow => new RarityLeaderboardRow(
                $player,
                $this->filter,
                $this->utility,
                $this->highlightedPlayerId,
                $this->filterParameters
            ),
            $players
        );
    }

    public function getTitle(): string
    {
        return self::TITLE;
    }

    /**
     * @return RarityLeaderboardRow[]
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * @return array<string, string>
     */
    public function getFilterQueryParameters(): array
    {
        return $this->filterParameters;
    }

    /**
     * @return array<string, string>
     */
    public function getCurrentPageQueryParameters(): array
    {
        return $this->currentPageParameters;
    }

    public function shouldShowCountryRank(): bool
    {
        return $this->filter->hasCountry();
    }

    public function getLeaderboardPage(): PlayerLeaderboardPage
    {
        return $this->leaderboardPage;
    }
}
