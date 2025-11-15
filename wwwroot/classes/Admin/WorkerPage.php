<?php

declare(strict_types=1);

require_once __DIR__ . '/AdminRequest.php';
require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/CommandExecutionResult.php';
require_once __DIR__ . '/WorkerPageSortLink.php';
require_once __DIR__ . '/WorkerPageResult.php';

final class WorkerPage
{
    private WorkerService $workerService;

    public function __construct(WorkerService $workerService)
    {
        $this->workerService = $workerService;
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public function handle(array $queryParameters, AdminRequest $request): WorkerPageResult
    {
        $sortField = $this->normalizeSortField($queryParameters['sort'] ?? null);
        $sortDirection = $this->normalizeSortDirection($queryParameters['direction'] ?? null);

        [$successMessage, $errorMessage] = $request->isPost()
            ? $this->processAction($request)
            : [null, null];

        $workers = $this->workerService->fetchWorkers($sortField, strtoupper($sortDirection));

        $sortLinks = [
            'id' => $this->createSortLink('id', $sortField, $sortDirection),
            'scan_start' => $this->createSortLink('scan_start', $sortField, $sortDirection),
        ];

        return new WorkerPageResult(
            $workers,
            $successMessage,
            $errorMessage,
            $sortLinks,
            $sortField,
            $sortDirection
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function processAction(AdminRequest $request): array
    {
        $action = $request->getPostString('action');

        return match ($action) {
            'update_npsso' => $this->processUpdateNpsso($request),
            'restart_worker' => $this->processRestartWorker($request),
            'restart_all_workers' => $this->processRestartAllWorkers(),
            default => [null, 'Unsupported action requested.'],
        };
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function processUpdateNpsso(AdminRequest $request): array
    {
        $workerId = $request->getPostPositiveInt('worker_id');

        if ($workerId === null) {
            return [null, 'Invalid worker selected.'];
        }

        $npsso = $request->getPostString('npsso');

        if ($npsso === '') {
            return [null, 'The NPSSO value cannot be empty.'];
        }

        if (strlen($npsso) > 64) {
            return [null, 'The NPSSO value must be 64 characters or fewer.'];
        }

        try {
            $updated = $this->workerService->updateWorkerNpsso($workerId, $npsso);
        } catch (Throwable $exception) {
            return [null, 'An unexpected error occurred while updating the NPSSO value.'];
        }

        if ($updated) {
            return ['Worker NPSSO updated successfully.', null];
        }

        return [null, 'Unable to update NPSSO. Please verify the worker still exists.'];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function processRestartWorker(AdminRequest $request): array
    {
        $workerId = $request->getPostPositiveInt('worker_id');

        if ($workerId === null) {
            return [null, 'Invalid worker selected for restart.'];
        }

        $result = $this->workerService->restartWorker($workerId);

        if ($result->isSuccessful()) {
            return [$this->appendCommandOutput(
                sprintf('Worker #%d restart signal sent successfully.', $workerId),
                $result
            ), null];
        }

        if ($result->getExitCode() === 1) {
            return [null, sprintf(
                'No running process matched worker #%d. It may already be stopped.',
                $workerId
            )];
        }

        return [null, $this->appendCommandOutput(
            sprintf('Unable to restart worker #%d (exit code %d).', $workerId, $result->getExitCode()),
            $result
        )];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function processRestartAllWorkers(): array
    {
        $result = $this->workerService->restartAllWorkers();

        if ($result->isSuccessful()) {
            return [$this->appendCommandOutput('All workers received the restart signal.', $result), null];
        }

        if ($result->getExitCode() === 1) {
            return [null, 'No worker processes matched the restart request. They may already be stopped.'];
        }

        return [null, $this->appendCommandOutput(
            sprintf('Unable to restart all workers (exit code %d).', $result->getExitCode()),
            $result
        )];
    }

    private function appendCommandOutput(string $message, CommandExecutionResult $result): string
    {
        $output = trim($result->getOutput());

        if ($output === '') {
            return $message;
        }

        return $message . ' ' . $output;
    }

    private function normalizeSortField(mixed $value): string
    {
        if (!is_string($value)) {
            return 'scan_start';
        }

        $normalized = strtolower(trim($value));

        return $normalized === 'id' ? 'id' : 'scan_start';
    }

    private function normalizeSortDirection(mixed $value): string
    {
        if (!is_string($value)) {
            return 'asc';
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, ['asc', 'desc'], true) ? $normalized : 'asc';
    }

    private function createSortLink(string $field, string $currentField, string $currentDirection): WorkerPageSortLink
    {
        $isActive = $field === $currentField;
        $indicator = '';
        $nextDirection = 'asc';

        if ($isActive) {
            $indicator = $currentDirection === 'asc' ? ' ▲' : ' ▼';
            $nextDirection = $currentDirection === 'asc' ? 'desc' : 'asc';
        }

        $query = [
            'sort' => $field,
            'direction' => $nextDirection,
        ];

        return new WorkerPageSortLink($field, '?' . http_build_query($query), $indicator);
    }
}
