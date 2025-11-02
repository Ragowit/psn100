<?php

declare(strict_types=1);

require_once __DIR__ . '/LogService.php';
require_once __DIR__ . '/LogPageResult.php';

final class LogPage
{
    private LogService $logService;

    private int $entriesPerPage;

    public function __construct(LogService $logService, int $entriesPerPage = 50)
    {
        $this->logService = $logService;
        $this->entriesPerPage = max(1, $entriesPerPage);
    }

    /**
     * @param array<string, mixed> $queryParameters
     * @param array<string, mixed> $postData
     */
    public function handle(array $queryParameters, array $postData, string $method): LogPageResult
    {
        $successMessage = null;
        $errorMessage = null;

        if (strtoupper($method) === 'POST' && array_key_exists('delete_id', $postData)) {
            $deleteId = $this->parsePositiveInt($postData['delete_id'] ?? null);

            if ($deleteId === null) {
                $errorMessage = 'Please provide a valid log entry ID to delete.';
            } else {
                $deleted = $this->logService->deleteLogById($deleteId);

                if ($deleted) {
                    $successMessage = sprintf('Log entry %d deleted successfully.', $deleteId);
                } else {
                    $errorMessage = sprintf('Log entry %d could not be found.', $deleteId);
                }
            }
        }

        $requestedPage = $this->parsePositiveInt($queryParameters['page'] ?? null) ?? 1;

        $totalEntries = $this->logService->countEntries();
        $totalPages = (int) ceil(max(0, $totalEntries) / $this->entriesPerPage);
        $totalPages = max(1, $totalPages);

        $currentPage = min($requestedPage, $totalPages);
        $currentPage = max(1, $currentPage);

        $entries = $this->logService->fetchEntriesForPage($currentPage, $this->entriesPerPage);

        if ($entries === [] && $totalEntries > 0 && $currentPage > 1) {
            $currentPage = max(1, $currentPage - 1);
            $entries = $this->logService->fetchEntriesForPage($currentPage, $this->entriesPerPage);
        }

        return new LogPageResult($entries, $currentPage, $totalPages, $successMessage, $errorMessage);
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || !ctype_digit($trimmed)) {
            return null;
        }

        $intValue = (int) $trimmed;

        return $intValue > 0 ? $intValue : null;
    }
}
