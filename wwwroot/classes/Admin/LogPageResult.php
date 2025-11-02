<?php

declare(strict_types=1);

require_once __DIR__ . '/LogEntry.php';

final class LogPageResult
{
    /**
     * @var list<LogEntry>
     */
    private array $entries;

    private int $currentPage;

    private int $totalPages;

    private ?string $successMessage;

    private ?string $errorMessage;

    /**
     * @param list<LogEntry> $entries
     */
    public function __construct(array $entries, int $currentPage, int $totalPages, ?string $successMessage, ?string $errorMessage)
    {
        $this->entries = $entries;
        $this->currentPage = max(1, $currentPage);
        $this->totalPages = max(1, $totalPages);
        $this->successMessage = $successMessage;
        $this->errorMessage = $errorMessage;
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
