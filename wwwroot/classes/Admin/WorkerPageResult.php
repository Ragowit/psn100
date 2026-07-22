<?php

declare(strict_types=1);

require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/WorkerPageSortLink.php';
require_once __DIR__ . '/WorkerSortField.php';
require_once __DIR__ . '/WorkerSortDirection.php';

final readonly class WorkerPageResult
{
    /**
     * @var list<Worker>
     */
    private readonly array $workers;

    /**
     * @param list<Worker> $workers
     * @param array<string, WorkerPageSortLink> $sortLinks
     */
    public function __construct(
        array $workers,
        private readonly ?string $successMessage,
        private readonly ?string $errorMessage,
        private readonly array $sortLinks,
        private readonly WorkerSortField $sortField,
        private readonly WorkerSortDirection $sortDirection,
    ) {
        $this->workers = array_values($workers);
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

    public function getSortField(): WorkerSortField
    {
        return $this->sortField;
    }

    public function getSortDirection(): WorkerSortDirection
    {
        return $this->sortDirection;
    }
}
