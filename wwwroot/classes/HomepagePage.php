<?php

declare(strict_types=1);

require_once __DIR__ . '/HomepageContentService.php';
require_once __DIR__ . '/HomepageViewModel.php';

class HomepagePage
{
    private const DEFAULT_TITLE = 'PSN 100% ~ PlayStation Leaderboards & Trophies';

    private HomepageContentService $contentService;

    private string $title = self::DEFAULT_TITLE;

    private ?int $newGamesLimit = null;

    private ?int $newDlcsLimit = null;

    private ?int $popularGamesLimit = null;

    public function __construct(HomepageContentService $contentService)
    {
        $this->contentService = $contentService;
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

    public function buildViewModel(): HomepageViewModel
    {
        $newGames = $this->newGamesLimit === null
            ? $this->contentService->getNewGames()
            : $this->contentService->getNewGames($this->newGamesLimit);

        $newDlcs = $this->newDlcsLimit === null
            ? $this->contentService->getNewDlcs()
            : $this->contentService->getNewDlcs($this->newDlcsLimit);

        $popularGames = $this->popularGamesLimit === null
            ? $this->contentService->getPopularGames()
            : $this->contentService->getPopularGames($this->popularGamesLimit);

        return new HomepageViewModel($this->title, $newGames, $newDlcs, $popularGames);
    }

    private function assertPositiveLimit(int $limit): void
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Limit must be greater than or equal to 1.');
        }
    }
}
