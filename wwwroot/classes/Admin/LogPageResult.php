<?php

declare(strict_types=1);

require_once __DIR__ . '/LogEntry.php';

final readonly class LogPageResult
{
    private int $currentPage;

    private int $totalPages;

    /**
     * @param list<LogEntry> $entries
     */
    public function __construct(
        final private array $entries,
        int $currentPage,
        int $totalPages,
        final private ?string $successMessage,
        final private ?string $errorMessage,
    ) {
        $this->currentPage = max(1, $currentPage);
        $this->totalPages = max(1, $totalPages);
    }

    /**
     * @return list<LogEntry>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function getSuccessMessage(): ?string
    {
        return $this->successMessage;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
