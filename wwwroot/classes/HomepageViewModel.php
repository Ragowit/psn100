<?php

declare(strict_types=1);

require_once __DIR__ . '/Homepage/HomepageNewGame.php';
require_once __DIR__ . '/Homepage/HomepageDlc.php';
require_once __DIR__ . '/Homepage/HomepagePopularGame.php';
require_once __DIR__ . '/HomepagePopularGamesFilter.php';

final readonly class HomepageViewModel
{
    private HomepagePopularGamesFilter $popularGamesFilter;

    /**
     * @param HomepageNewGame[] $newGames
     * @param HomepageDlc[] $newDlcs
     * @param HomepagePopularGame[] $popularGames
     */
    public function __construct(
        final private string $title,
        final private array $newGames,
        final private array $newDlcs,
        final private array $popularGames,
        ?HomepagePopularGamesFilter $popularGamesFilter = null,
    ) {
        $this->popularGamesFilter = $popularGamesFilter ?? HomepagePopularGamesFilter::fromArray([]);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return HomepageNewGame[]
     */
    public function getNewGames(): array
    {
        return $this->newGames;
    }

    /**
     * @return HomepageDlc[]
     */
    public function getNewDlcs(): array
    {
        return $this->newDlcs;
    }

    /**
     * @return HomepagePopularGame[]
     */
    public function getPopularGames(): array
    {
        return $this->popularGames;
    }

    public function getPopularGamesFilter(): HomepagePopularGamesFilter
    {
        return $this->popularGamesFilter;
    }
}
