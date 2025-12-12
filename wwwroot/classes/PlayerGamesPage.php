<?php

declare(strict_types=1);

require_once __DIR__ . '/ChangelogPaginator.php';
require_once __DIR__ . '/PlayerGamesFilter.php';
require_once __DIR__ . '/PlayerGamesService.php';
require_once __DIR__ . '/PlayerStatus.php';

class PlayerGamesPage
{
    private PlayerGamesFilter $requestedFilter;

    private ChangelogPaginator $paginator;

    /**
     * @var PlayerGame[]
     */
    private array $games;

    public function __construct(
        PlayerGamesService $service,
        PlayerGamesFilter $filter,
        int $accountId,
        PlayerStatus $playerStatus
    ) {
        $this->requestedFilter = $filter;

        $limit = $filter->getLimit();
        $shouldLoadGames = $this->shouldLoadPlayerGames($playerStatus);
        $totalGames = 0;

        if ($shouldLoadGames) {
            $totalGames = $service->countPlayerGames($accountId, $filter);
        }

        $this->paginator = new ChangelogPaginator($filter->getPage(), $totalGames, $limit);

        if ($shouldLoadGames && $totalGames > 0) {
            $resolvedFilter = $this->createFilterForPage($this->paginator->getCurrentPage());
            $this->games = $service->getPlayerGames($accountId, $resolvedFilter);
        } else {
            $this->games = [];
        }
    }

    /**
     * @return PlayerGame[]
     */
    public function getGames(): array
    {
        return $this->games;
    }

    public function getTotalGames(): int
    {
        return $this->paginator->getTotalCount();
    }

    public function getRangeStart(): int
    {
        return $this->paginator->getRangeStart();
    }

    public function getRangeEnd(): int
    {
        return $this->paginator->getRangeEnd();
    }

    public function getCurrentPage(): int
    {
        return $this->paginator->getCurrentPage();
    }

    public function getTotalPages(): int
    {
        return $this->paginator->getTotalPages();
    }

    public function hasPreviousPage(): bool
    {
        return $this->paginator->hasPreviousPage();
    }

    public function getPreviousPage(): int
    {
        return $this->paginator->getPreviousPage();
    }

    public function hasNextPage(): bool
    {
        return $this->paginator->hasNextPage();
    }

    public function getNextPage(): int
    {
        return $this->paginator->getNextPage();
    }

    public function shouldShowFirstPage(): bool
    {
        return $this->getTotalPages() > 0 && $this->getCurrentPage() > 3;
    }

    public function shouldShowLeadingEllipsis(): bool
    {
        return $this->shouldShowFirstPage();
    }

    public function shouldShowLastPage(): bool
    {
        return $this->getTotalPages() > 0 && $this->getCurrentPage() < $this->getLastPage() - 2;
    }

    public function shouldShowTrailingEllipsis(): bool
    {
        return $this->shouldShowLastPage();
    }

    public function getFirstPage(): int
    {
        return 1;
    }

    public function getLastPage(): int
    {
        return $this->paginator->getLastPageNumber();
    }

    /**
     * @return int[]
     */
    public function getPreviousPages(): array
    {
        $pages = [];

        for ($i = 2; $i >= 1; $i--) {
            $candidate = $this->getCurrentPage() - $i;

            if ($candidate > 0) {
                $pages[] = $candidate;
            }
        }

        return $pages;
    }

    /**
     * @return int[]
     */
    public function getNextPages(): array
    {
        $pages = [];
        $lastPage = $this->getLastPage();

        for ($i = 1; $i <= 2; $i++) {
            $candidate = $this->getCurrentPage() + $i;

            if ($candidate <= $lastPage) {
                $pages[] = $candidate;
            }
        }

        return $pages;
    }

    /**
     * @return array<string, int|string>
     */
    public function getPageQueryParameters(int $page): array
    {
        return $this->requestedFilter->withPage($page);
    }

    private function shouldLoadPlayerGames(PlayerStatus $playerStatus): bool
    {
        return $playerStatus->isVisible();
    }

    private function createFilterForPage(int $page): PlayerGamesFilter
    {
        if ($this->requestedFilter->getPage() === $page) {
            return $this->requestedFilter;
        }

        return $this->requestedFilter->withPageNumber($page);
    }
}
