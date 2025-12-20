<?php

declare(strict_types=1);

require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/WorkerPageSortLink.php';

final readonly class WorkerPageResult
{
    /**
     * @var list<Worker>
     */
    private readonly array $workers;

    private readonly ?string $successMessage;

    private readonly ?string $errorMessage;

    /**
     * @var array<string, WorkerPageSortLink>
     */
    private readonly array $sortLinks;

    private readonly string $sortField;

    private readonly string $sortDirection;

    /**
     * @param list<Worker> $workers
     * @param array<string, WorkerPageSortLink> $sortLinks
     */
    public function __construct(
        array $workers,
        ?string $successMessage,
        ?string $errorMessage,
        array $sortLinks,
        string $sortField,
        string $sortDirection
    ) {
        $this->workers = array_values($workers);
        $this->successMessage = $successMessage;
        $this->errorMessage = $errorMessage;
        $this->sortLinks = $sortLinks;
        $this->sortField = $sortField;
        $this->sortDirection = $sortDirection;
    }

    /**
     * @return list<Worker>
     */
    public function getWorkers(): array
    {
        return $this->workers;
    }

    public function getSuccessMessage(): ?string
    {
        return $this->successMessage;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return array<string, WorkerPageSortLink>
     */
    public function getSortLinks(): array
    {
        return $this->sortLinks;
    }

    public function getSortLink(string $field): ?WorkerPageSortLink
    {
        return $this->sortLinks[$field] ?? null;
    }

    public function getSortField(): string
    {
        return $this->sortField;
    }

    public function getSortDirection(): string
    {
        return $this->sortDirection;
    }
}
