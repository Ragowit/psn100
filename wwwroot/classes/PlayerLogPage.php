<?php

declare(strict_types=1);

require_once __DIR__ . '/ChangelogPaginator.php';
require_once __DIR__ . '/PlayerLogFilter.php';
require_once __DIR__ . '/PlayerLogService.php';

class PlayerLogPage
{
    private const STATUS_FLAGGED = 1;
    private const STATUS_PRIVATE = 3;

    private PlayerLogFilter $requestedFilter;

    private ChangelogPaginator $paginator;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $trophies;

    public function __construct(
        PlayerLogService $service,
        PlayerLogFilter $filter,
        int $accountId,
        int $playerStatus
    ) {
        $this->requestedFilter = $filter;

        $limit = PlayerLogService::PAGE_SIZE;
        $shouldLoadLog = $this->shouldLoadPlayerLog($playerStatus);
        $totalTrophies = 0;

        if ($shouldLoadLog) {
            $totalTrophies = $service->countTrophies($accountId, $filter);
        }

        $this->paginator = new ChangelogPaginator($filter->getPage(), $totalTrophies, $limit);

        if ($shouldLoadLog && $this->paginator->getTotalCount() > 0) {
            $this->trophies = $service->getTrophies(
                $accountId,
                $filter,
                $this->paginator->getOffset(),
                $this->paginator->getLimit()
            );
        } else {
            $this->trophies = [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTrophies(): array
    {
        return $this->trophies;
    }

    public function getTotalTrophies(): int
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

    /**
     * @return array<string, string>
     */
    public function getFilterParameters(): array
    {
        return $this->requestedFilter->getFilterParameters();
    }

    private function shouldLoadPlayerLog(int $playerStatus): bool
    {
        return !in_array($playerStatus, [self::STATUS_FLAGGED, self::STATUS_PRIVATE], true);
    }
}
