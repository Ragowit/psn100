<?php

declare(strict_types=1);

require_once __DIR__ . '/HomepageContentService.php';
require_once __DIR__ . '/HomepageViewModel.php';
require_once __DIR__ . '/HomepagePopularGamesFilter.php';

class HomepagePage
{
    private const DEFAULT_TITLE = 'PSN 100% ~ PlayStation Leaderboards & Trophies';

    public function __construct(
        private readonly HomepageContentService $contentService,
        private string $title = self::DEFAULT_TITLE,
        private ?int $newGamesLimit = null,
        private ?int $newDlcsLimit = null,
        private ?int $popularGamesLimit = null,
        private ?HomepagePopularGamesFilter $popularGamesFilter = null,
    ) {
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setNewGamesLimit(int $limit): self
    {
        $this->assertPositiveLimit($limit);
        $this->newGamesLimit = $limit;

        return $this;
    }

    public function setNewDlcsLimit(int $limit): self
    {
        $this->assertPositiveLimit($limit);
        $this->newDlcsLimit = $limit;

        return $this;
    }

    public function setPopularGamesLimit(int $limit): self
    {
        $this->assertPositiveLimit($limit);
        $this->popularGamesLimit = $limit;

        return $this;
    }

    public function setPopularGamesFilter(HomepagePopularGamesFilter $filter): self
    {
        $this->popularGamesFilter = $filter;

        return $this;
    }

    public function buildViewModel(): HomepageViewModel
    {
        $newGames = $this->newGamesLimit === null
            ? $this->contentService->getNewGames()
            : $this->contentService->getNewGames($this->newGamesLimit);

        $newDlcs = $this->newDlcsLimit === null
            ? $this->contentService->getNewDlcs()
            : $this->contentService->getNewDlcs($this->newDlcsLimit);

        $popularGamesFilter = $this->popularGamesFilter ?? HomepagePopularGamesFilter::fromArray([]);

        $popularGames = $this->popularGamesLimit === null
            ? $this->contentService->getPopularGames(filter: $popularGamesFilter)
            : $this->contentService->getPopularGames($this->popularGamesLimit, $popularGamesFilter);

        return new HomepageViewModel($this->title, $newGames, $newDlcs, $popularGames, $popularGamesFilter);
    }

    private function assertPositiveLimit(int $limit): void
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Limit must be greater than or equal to 1.');
        }
    }
}
