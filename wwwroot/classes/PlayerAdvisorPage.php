<?php

declare(strict_types=1);

class PlayerAdvisorPage
{
    private PlayerAdvisorService $playerAdvisorService;

    private PlayerSummaryService $playerSummaryService;

    private PlayerAdvisorFilter $filter;

    private int $accountId;

    private int $playerStatus;

    private ?PlayerSummary $playerSummary = null;

    private ?int $totalTrophies = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $advisableTrophies = null;

    public function __construct(
        PlayerAdvisorService $playerAdvisorService,
        PlayerSummaryService $playerSummaryService,
        PlayerAdvisorFilter $filter,
        int $accountId,
        int $playerStatus
    ) {
        $this->playerAdvisorService = $playerAdvisorService;
        $this->playerSummaryService = $playerSummaryService;
        $this->filter = $filter;
        $this->accountId = $accountId;
        $this->playerStatus = $playerStatus;
    }

    public function getPlayerSummary(): PlayerSummary
    {
        if ($this->playerSummary === null) {
            $this->playerSummary = $this->playerSummaryService->getSummary($this->accountId);
        }

        return $this->playerSummary;
    }

    public function getFilter(): PlayerAdvisorFilter
    {
        return $this->filter;
    }

    public function getCurrentPage(): int
    {
        return $this->filter->getPage();
    }

    public function getPageSize(): int
    {
        return PlayerAdvisorService::PAGE_SIZE;
    }

    public function getOffset(): int
    {
        return $this->filter->getOffset($this->getPageSize());
    }

    public function shouldDisplayAdvisor(): bool
    {
        return !in_array($this->playerStatus, [1, 3], true);
    }

    public function getTotalTrophies(): int
    {
        if (!$this->shouldDisplayAdvisor()) {
            return 0;
        }

        if ($this->totalTrophies === null) {
            $this->totalTrophies = $this->playerAdvisorService->countAdvisableTrophies(
                $this->accountId,
                $this->filter
            );
        }

        return $this->totalTrophies;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAdvisableTrophies(): array
    {
        if (!$this->shouldDisplayAdvisor()) {
            return [];
        }

        if ($this->advisableTrophies === null) {
            $this->advisableTrophies = $this->playerAdvisorService->getAdvisableTrophies(
                $this->accountId,
                $this->filter,
                $this->getOffset(),
                $this->getPageSize()
            );
        }

        return $this->advisableTrophies;
    }

    public function getTotalPages(): int
    {
        $pageSize = $this->getPageSize();

        if ($pageSize === 0) {
            return 0;
        }

        return (int) ceil($this->getTotalTrophies() / $pageSize);
    }

    /**
     * @return array<string, string>
     */
    public function getFilterParameters(): array
    {
        return $this->filter->getFilterParameters();
    }
}
