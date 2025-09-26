<?php

declare(strict_types=1);

require_once __DIR__ . '/Homepage/HomepageNewGame.php';
require_once __DIR__ . '/Homepage/HomepageDlc.php';
require_once __DIR__ . '/Homepage/HomepagePopularGame.php';

class HomepageViewModel
{
    private string $title;

    /**
     * @var HomepageNewGame[]
     */
    private array $newGames;

    /**
     * @var HomepageDlc[]
     */
    private array $newDlcs;

    /**
     * @var HomepagePopularGame[]
     */
    private array $popularGames;

    /**
     * @param HomepageNewGame[] $newGames
     * @param HomepageDlc[] $newDlcs
     * @param HomepagePopularGame[] $popularGames
     */
    public function __construct(string $title, array $newGames, array $newDlcs, array $popularGames)
    {
        $this->title = $title;
        $this->newGames = $newGames;
        $this->newDlcs = $newDlcs;
        $this->popularGames = $popularGames;
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
}
