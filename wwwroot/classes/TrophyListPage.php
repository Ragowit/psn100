<?php

declare(strict_types=1);

require_once __DIR__ . '/ChangelogPaginator.php';
require_once __DIR__ . '/TrophyListFilter.php';
require_once __DIR__ . '/TrophyListService.php';

class TrophyListPage
{
    private TrophyListFilter $filter;

    private ChangelogPaginator $paginator;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $trophies;

    public function __construct(TrophyListService $service, TrophyListFilter $filter)
    {
        $this->filter = $filter;

        $totalTrophies = $service->countTrophies();
        $this->paginator = new ChangelogPaginator(
            $filter->getPage(),
            $totalTrophies,
            TrophyListService::PAGE_SIZE
        );

        $this->trophies = $service->getTrophies(
            $this->paginator->getOffset(),
            $this->paginator->getLimit()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTrophies(): array
    {
        return $this->trophies;
    }

    public function getRangeStart(): int
    {
        return $this->paginator->getRangeStart();
    }

    public function getRangeEnd(): int
    {
        return $this->paginator->getRangeEnd();
    }

    public function getTotalCount(): int
    {
        return $this->paginator->getTotalCount();
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
     * @return array<string, int>
     */
    public function getPageQueryParameters(int $page): array
    {
        return $this->filter->withPage($page);
    }
}

