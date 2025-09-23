<?php

declare(strict_types=1);

require_once __DIR__ . '/AvatarService.php';

class AvatarPage
{
    private AvatarService $avatarService;

    private int $currentPage;

    private int $limit;

    private int $totalAvatarCount;

    private int $totalPages;

    /**
     * @var Avatar[]
     */
    private array $avatars;

    public function __construct(AvatarService $avatarService, int $currentPage = 1, int $limit = 48)
    {
        $this->avatarService = $avatarService;
        $this->limit = max($limit, 1);
        $this->currentPage = max($currentPage, 1);

        $this->totalAvatarCount = $this->avatarService->getTotalUniqueAvatarCount();
        $this->totalPages = $this->totalAvatarCount > 0
            ? (int) ceil($this->totalAvatarCount / $this->limit)
            : 0;

        $this->avatars = $this->avatarService->getAvatars($this->currentPage, $this->limit);
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function fromQueryParameters(AvatarService $avatarService, array $queryParameters, int $limit = 48): self
    {
        $page = 1;

        if (isset($queryParameters['page']) && is_numeric($queryParameters['page'])) {
            $page = (int) $queryParameters['page'];
        }

        return new self($avatarService, $page, $limit);
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function getTotalAvatarCount(): int
    {
        return $this->totalAvatarCount;
    }

    /**
     * @return Avatar[]
     */
    public function getAvatars(): array
    {
        return $this->avatars;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function getPreviousPage(): int
    {
        return max(1, $this->currentPage - 1);
    }

    public function hasNextPage(): bool
    {
        return $this->totalPages > 0 && $this->currentPage < $this->totalPages;
    }

    public function getNextPage(): int
    {
        return min($this->currentPage + 1, $this->totalPages);
    }

    /**
     * @return int[]
     */
    public function getPreviousPages(): array
    {
        $pages = [];

        for ($i = 2; $i >= 1; $i--) {
            $candidate = $this->currentPage - $i;

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

        if ($this->totalPages === 0) {
            return $pages;
        }

        for ($i = 1; $i <= 2; $i++) {
            $candidate = $this->currentPage + $i;

            if ($candidate <= $this->totalPages) {
                $pages[] = $candidate;
            }
        }

        return $pages;
    }

    public function shouldShowFirstPage(): bool
    {
        return $this->totalPages > 0 && $this->currentPage > 3;
    }

    public function shouldShowLeadingEllipsis(): bool
    {
        return $this->shouldShowFirstPage();
    }

    public function shouldShowLastPage(): bool
    {
        return $this->totalPages > 0 && $this->currentPage < $this->totalPages - 2;
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
        return $this->totalPages > 0 ? $this->totalPages : 1;
    }
}
