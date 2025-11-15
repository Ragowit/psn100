<?php

declare(strict_types=1);

require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/CommandExecutionResult.php';
require_once __DIR__ . '/CommandExecutorInterface.php';
require_once __DIR__ . '/SystemCommandExecutor.php';

final class WorkerService
{
    private PDO $database;

    private CommandExecutorInterface $commandExecutor;

    private const WORKER_USERNAME = 'psn100';

    private const WORKER_SCRIPT = '30th_minute.php';

    public function __construct(PDO $database, ?CommandExecutorInterface $commandExecutor = null)
    {
        $this->database = $database;
        $this->commandExecutor = $commandExecutor ?? new SystemCommandExecutor();
    }

    /**
     * @return list<Worker>
     */
    public function fetchWorkers(string $orderBy = 'scan_start', string $direction = 'ASC'): array
    {
        $orderColumn = match (strtolower($orderBy)) {
            'id' => 'id',
            default => 'scan_start',
        };

        $orderDirection = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $statement = $this->database->query(sprintf(
            'SELECT id, npsso, scanning, scan_start, scan_progress FROM setting ORDER BY %s %s',
            $orderColumn,
            $orderDirection
        ));

        if ($statement === false) {
            return [];
        }

        $workers = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $npsso = (string) ($row['npsso'] ?? '');
            $scanning = (string) ($row['scanning'] ?? '');
            $scanStartRaw = (string) ($row['scan_start'] ?? '');
            $scanProgressValue = $row['scan_progress'] ?? null;

            try {
                $scanStart = new DateTimeImmutable($scanStartRaw ?: '1970-01-01 00:00:00');
            } catch (Exception $exception) {
                $scanStart = new DateTimeImmutable('1970-01-01 00:00:00');
            }

            $scanProgress = $this->decodeScanProgress(
                is_string($scanProgressValue) ? $scanProgressValue : null
            );

            $workers[] = new Worker($id, $npsso, $scanning, $scanStart, $scanProgress);
        }

        return $workers;
    }

    private function decodeScanProgress(?string $value): ?WorkerScanProgress
    {
        return WorkerScanProgress::fromJson($value);
    }

    public function updateWorkerNpsso(int $workerId, string $npsso): bool
    {
        $statement = $this->database->prepare('UPDATE setting SET npsso = :npsso WHERE id = :id');

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':npsso', $npsso, PDO::PARAM_STR);
        $statement->bindValue(':id', $workerId, PDO::PARAM_INT);

        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function restartWorker(int $workerId): CommandExecutionResult
    {
        return $this->commandExecutor->run([
            'pkill',
            '-u',
            self::WORKER_USERNAME,
            '-f',
            sprintf('worker=%d([^0-9]|$)', $workerId),
        ]);
    }

    public function restartAllWorkers(): CommandExecutionResult
    {
        return $this->commandExecutor->run([
            'pkill',
            '-u',
            self::WORKER_USERNAME,
            '-f',
            self::WORKER_SCRIPT,
        ]);
    }
}
