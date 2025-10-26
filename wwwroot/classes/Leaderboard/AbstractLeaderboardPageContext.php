<?php

declare(strict_types=1);

require_once __DIR__ . '/../PlayerLeaderboardFilter.php';
require_once __DIR__ . '/../PlayerLeaderboardPage.php';
require_once __DIR__ . '/../PlayerLeaderboardDataProvider.php';
require_once __DIR__ . '/../Utility.php';
require_once __DIR__ . '/AbstractLeaderboardRow.php';

abstract class AbstractLeaderboardPageContext
{
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
     * @var AbstractLeaderboardRow[]
     */
    private array $rows;

    /**
     * @param array<string, mixed> $queryParameters
     */
    protected function __construct(
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
    final public static function fromGlobals(PDO $database, Utility $utility, array $queryParameters): static
    {
        $dataProvider = static::createDataProvider($database);
        $playerLeaderboardFilter = PlayerLeaderboardFilter::fromArray($queryParameters);
        $playerLeaderboardPage = new PlayerLeaderboardPage($dataProvider, $playerLeaderboardFilter);

        return new static($playerLeaderboardPage, $playerLeaderboardFilter, $utility, $queryParameters);
    }

    abstract public function getTitle(): string;

    /**
     * @return AbstractLeaderboardRow[]
     */
    final public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * @return array<string, string>
     */
    final public function getFilterQueryParameters(): array
    {
        return $this->filterParameters;
    }

    /**
     * @return array<string, string>
     */
    final public function getCurrentPageQueryParameters(): array
    {
        return $this->currentPageParameters;
    }

    final public function shouldShowCountryRank(): bool
    {
        return $this->filter->hasCountry();
    }

    final public function getLeaderboardPage(): PlayerLeaderboardPage
    {
        return $this->leaderboardPage;
    }

    abstract protected static function createDataProvider(PDO $database): PlayerLeaderboardDataProvider;

    /**
     * @param array<string, mixed> $player
     */
    abstract protected function createRow(
        array $player,
        PlayerLeaderboardFilter $filter,
        Utility $utility,
        ?string $highlightedPlayerId,
        array $filterParameters
    ): AbstractLeaderboardRow;

    /**
     * @return AbstractLeaderboardRow[]
     */
    private function buildRows(): array
    {
        $players = $this->leaderboardPage->getPlayers();

        return array_map(
            fn (array $player): AbstractLeaderboardRow => $this->createRow(
                $player,
                $this->filter,
                $this->utility,
                $this->highlightedPlayerId,
                $this->filterParameters
            ),
            $players
        );
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
}

