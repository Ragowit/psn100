<?php

declare(strict_types=1);

class ChangelogPaginator
{
    private int $currentPage;
    private int $totalCount;
    private int $limit;
    private int $totalPages;

    public function __construct(int $requestedPage, int $totalCount, int $limit)
    {
        $this->totalCount = max($totalCount, 0);
        $this->limit = max($limit, 1);
        $this->totalPages = $this->totalCount > 0 ? (int) ceil($this->totalCount / $this->limit) : 0;

        if ($this->totalPages > 0) {
            $this->currentPage = min(max($requestedPage, 1), $this->totalPages);
        } else {
            $this->currentPage = 1;
        }
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->limit;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function hasResults(): bool
    {
        return $this->totalCount > 0;
    }

    public function getRangeStart(): int
    {
        return $this->hasResults() ? $this->getOffset() + 1 : 0;
    }

    public function getRangeEnd(): int
    {
        return $this->hasResults() ? min($this->getOffset() + $this->limit, $this->totalCount) : 0;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->getLastPageNumber();
    }

    public function getPreviousPage(): int
    {
        return max($this->currentPage - 1, 1);
    }

    public function getNextPage(): int
    {
        return min($this->currentPage + 1, $this->getLastPageNumber());
    }

    public function getLastPageNumber(): int
    {
        return max($this->totalPages, 1);
    }
}
