<?php

declare(strict_types=1);

require_once __DIR__ . '/PaginationItem.php';

final readonly class Pagination
{
    private int $currentPage;

    private int $totalPages;

    public function __construct(int $currentPage, int $totalPages)
    {
        $this->totalPages = max(1, $totalPages);
        $this->currentPage = min(max(1, $currentPage), $this->totalPages);
    }

    public static function create(int $currentPage, int $totalPages): self
    {
        return new self($currentPage, $totalPages);
    }

    /**
     * @return list<PaginationItem>
     */
    public function buildItems(): array
    {
        $items = [];

        if ($this->currentPage > 1) {
            $items[] = PaginationItem::forPage($this->currentPage - 1, '<')
                ->setAriaLabel('Previous');
        }

        if ($this->currentPage > 3) {
            $items[] = PaginationItem::forPage(1, '1');
            $items[] = PaginationItem::ellipsis();
        }

        for ($i = 2; $i >= 1; $i--) {
            $previousPage = $this->currentPage - $i;

            if ($previousPage > 0) {
                $items[] = PaginationItem::forPage($previousPage, (string) $previousPage);
            }
        }

        $items[] = PaginationItem::forPage($this->currentPage, (string) $this->currentPage)
            ->markAsActive();

        for ($i = 1; $i <= 2; $i++) {
            $nextPage = $this->currentPage + $i;

            if ($nextPage <= $this->totalPages) {
                $items[] = PaginationItem::forPage($nextPage, (string) $nextPage);
            }
        }

        if ($this->currentPage < $this->totalPages - 2) {
            $items[] = PaginationItem::ellipsis();
            $items[] = PaginationItem::forPage($this->totalPages, (string) $this->totalPages);
        }

        if ($this->currentPage < $this->totalPages) {
            $items[] = PaginationItem::forPage($this->currentPage + 1, '>')
                ->setAriaLabel('Next');
        }

        return $items;
    }
}
