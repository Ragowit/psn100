<?php

declare(strict_types=1);

require_once __DIR__ . '/GameListService.php';
require_once __DIR__ . '/GameListFilter.php';

class GameListPage
{
    private GameListService $gameListService;

    private GameListFilter $filter;

    private int $limit;

    private int $offset;

    private int $totalGames;

    private int $totalPages;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $games;

    /**
     * @var array<string, string>
     */
    private array $paginationParameters;

    public function __construct(GameListService $gameListService, GameListFilter $filter)
    {
        $this->gameListService = $gameListService;
        $this->filter = $filter->withPlayer($gameListService->resolvePlayer($filter->getPlayer()));
        $this->limit = $this->gameListService->getLimit();
        $this->offset = $this->gameListService->getOffset($this->filter);
        $this->totalGames = $this->gameListService->countGames($this->filter);
        $this->totalPages = $this->totalGames > 0
            ? (int) ceil($this->totalGames / $this->limit)
            : 0;
        $this->games = $this->gameListService->getGames($this->filter);
        $this->paginationParameters = $this->filter->getQueryParametersForPagination();
    }

    public function getFilter(): GameListFilter
    {
        return $this->filter;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGames(): array
    {
        return $this->games;
    }

    public function getPlayerName(): ?string
    {
        return $this->filter->getPlayer();
    }

    public function getTotalGames(): int
    {
        return $this->totalGames;
    }

    public function getRangeStart(): int
    {
        return $this->totalGames === 0 ? 0 : $this->offset + 1;
    }

    public function getRangeEnd(): int
    {
        return min($this->offset + $this->limit, $this->totalGames);
    }

    public function getCurrentPage(): int
    {
        return $this->filter->getPage();
    }

    public function getLastPage(): int
    {
        return $this->totalPages > 0 ? $this->totalPages : 1;
    }

    public function hasPreviousPage(): bool
    {
        return $this->getCurrentPage() > 1;
    }

    public function getPreviousPage(): int
    {
        return max(1, $this->getCurrentPage() - 1);
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

    public function hasNextPage(): bool
    {
        return $this->totalPages > 0 && $this->getCurrentPage() < $this->getLastPage();
    }

    public function getNextPage(): int
    {
        return min($this->getCurrentPage() + 1, $this->getLastPage());
    }

    /**
     * @return int[]
     */
    public function getNextPages(): array
    {
        $pages = [];

        if ($this->totalPages === 0) {
            return $pages;
        }

        for ($i = 1; $i <= 2; $i++) {
            $candidate = $this->getCurrentPage() + $i;

            if ($candidate <= $this->getLastPage()) {
                $pages[] = $candidate;
            }
        }

        return $pages;
    }

    public function shouldShowFirstPage(): bool
    {
        return $this->totalPages > 0 && $this->getCurrentPage() > 3;
    }

    public function shouldShowLeadingEllipsis(): bool
    {
        return $this->shouldShowFirstPage();
    }

    public function shouldShowLastPage(): bool
    {
        return $this->totalPages > 0 && $this->getCurrentPage() < $this->getLastPage() - 2;
    }

    public function shouldShowTrailingEllipsis(): bool
    {
        return $this->shouldShowLastPage();
    }

    public function getFirstPage(): int
    {
        return 1;
    }

    public function getPaginationParameters(): array
    {
        return $this->paginationParameters;
    }

    public function getPageQueryParameters(int $page): array
    {
        $parameters = $this->paginationParameters;
        $parameters['page'] = (string) $page;

        return $parameters;
    }

    public function getCurrentPageParameters(): array
    {
        return $this->getPageQueryParameters($this->getCurrentPage());
    }

    public function getLastPageParameters(): array
    {
        return $this->getPageQueryParameters($this->getLastPage());
    }
}
