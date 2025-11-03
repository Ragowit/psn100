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
    public function fetchWorkers(): array
    {
        $statement = $this->database->query(
            'SELECT id, npsso, scanning, scan_start FROM setting ORDER BY scan_start ASC'
        );

        if ($statement === false) {
            return [];
        }

        $workers = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $npsso = (string) ($row['npsso'] ?? '');
            $scanning = (string) ($row['scanning'] ?? '');
            $scanStartRaw = (string) ($row['scan_start'] ?? '');

            try {
                $scanStart = new DateTimeImmutable($scanStartRaw ?: '1970-01-01 00:00:00');
            } catch (Exception $exception) {
                $scanStart = new DateTimeImmutable('1970-01-01 00:00:00');
            }

            $workers[] = new Worker($id, $npsso, $scanning, $scanStart);
        }

        return $workers;
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
