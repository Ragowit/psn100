<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerLogFilter.php';
require_once __DIR__ . '/PlayerLogService.php';

class PlayerLogPage
{
    private const int STATUS_FLAGGED = 1;
    private const int STATUS_PRIVATE = 3;

    private PlayerLogFilter $requestedFilter;

    /**
     * @var PlayerLogEntry[]
     */
    private array $trophies = [];

    private int $totalTrophies = 0;

    public function __construct(
        PlayerLogService $service,
        PlayerLogFilter $filter,
        int $accountId,
        int $playerStatus
    ) {
        $this->requestedFilter = $filter->withPageNumber(1);

        if (!$this->shouldLoadPlayerLog($playerStatus)) {
            return;
        }

        $this->totalTrophies = $service->countTrophies($accountId, $this->requestedFilter);

        if ($this->totalTrophies === 0) {
            return;
        }

        $this->trophies = $service->getTrophies(
            $accountId,
            $this->requestedFilter,
            0,
            PlayerLogService::PAGE_SIZE
        );
    }

    /**
     * @return PlayerLogEntry[]
     */
    public function getTrophies(): array
    {
        return $this->trophies;
    }

    public function getTotalTrophies(): int
    {
        return $this->totalTrophies;
    }

    public function getRangeStart(): int
    {
        return $this->trophies === [] ? 0 : 1;
    }

    public function getRangeEnd(): int
    {
        return count($this->trophies);
    }

    public function getCurrentPage(): int
    {
        return 1;
    }

    public function getTotalPages(): int
    {
        return $this->trophies === [] ? 0 : 1;
    }

    public function hasPreviousPage(): bool
    {
        return false;
    }

    public function getPreviousPage(): int
    {
        return 1;
    }

    public function hasNextPage(): bool
    {
        return false;
    }

    public function getNextPage(): int
    {
        return 1;
    }

    public function shouldShowFirstPage(): bool
    {
        return false;
    }

    public function shouldShowLeadingEllipsis(): bool
    {
        return false;
    }

    public function shouldShowLastPage(): bool
    {
        return false;
    }

    public function shouldShowTrailingEllipsis(): bool
    {
        return false;
    }

    public function getFirstPage(): int
    {
        return 1;
    }

    public function getLastPage(): int
    {
        return 1;
    }

    /**
     * @return int[]
     */
    public function getPreviousPages(): array
    {
        return [];
    }

    /**
     * @return int[]
     */
    public function getNextPages(): array
    {
        return [];
    }

    /**
     * @return array<string, int|string>
     */
    public function getPageQueryParameters(int $page): array
    {
        return $this->requestedFilter->withPage(1);
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
