<?php

declare(strict_types=1);

require_once __DIR__ . '/Worker.php';

final class WorkerService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
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

    /**
     * @return array{current?: int, total?: int, title?: string, npCommunicationId?: string}|null
     */
    private function decodeScanProgress(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return null;
        }

        $progress = [];

        if (array_key_exists('current', $decoded) && is_numeric($decoded['current'])) {
            $progress['current'] = max(0, (int) $decoded['current']);
        }

        if (array_key_exists('total', $decoded) && is_numeric($decoded['total'])) {
            $progress['total'] = max(0, (int) $decoded['total']);
        }

        if (array_key_exists('title', $decoded) && is_string($decoded['title'])) {
            $progress['title'] = $decoded['title'];
        }

        if (array_key_exists('npCommunicationId', $decoded) && is_string($decoded['npCommunicationId'])) {
            $progress['npCommunicationId'] = $decoded['npCommunicationId'];
        }

        return $progress === [] ? null : $progress;
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
}
