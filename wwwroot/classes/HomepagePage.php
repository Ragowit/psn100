<?php

declare(strict_types=1);

require_once __DIR__ . '/HomepageContentService.php';
require_once __DIR__ . '/HomepageViewModel.php';
require_once __DIR__ . '/HomepagePopularGamesFilter.php';

final readonly class HomepagePage
{
    private const DEFAULT_TITLE = 'PSN 100% ~ PlayStation Leaderboards & Trophies';

    public function __construct(
        private HomepageContentService $contentService,
        private string $title = self::DEFAULT_TITLE,
        private ?int $newGamesLimit = null,
        private ?int $newDlcsLimit = null,
        private ?int $popularGamesLimit = null,
        private ?HomepagePopularGamesFilter $popularGamesFilter = null,
    ) {
    }

    #[\NoDiscard]
    public function withTitle(string $title): self
    {
        return clone($this, ['title' => $title]);
    }

    #[\NoDiscard]
    public function withNewGamesLimit(int $limit): self
    {
        self::assertPositiveLimit($limit);

        return clone($this, ['newGamesLimit' => $limit]);
    }

    #[\NoDiscard]
    public function withNewDlcsLimit(int $limit): self
    {
        self::assertPositiveLimit($limit);

        return clone($this, ['newDlcsLimit' => $limit]);
    }

    #[\NoDiscard]
    public function withPopularGamesLimit(int $limit): self
    {
        self::assertPositiveLimit($limit);

        return clone($this, ['popularGamesLimit' => $limit]);
    }

    #[\NoDiscard]
    public function withPopularGamesFilter(HomepagePopularGamesFilter $filter): self
    {
        return clone($this, ['popularGamesFilter' => $filter]);
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

    private static function assertPositiveLimit(int $limit): void
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Limit must be greater than or equal to 1.');
        }
    }
}
