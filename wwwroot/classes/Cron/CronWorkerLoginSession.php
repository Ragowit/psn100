<?php

declare(strict_types=1);

require_once __DIR__ . '/../Admin/PlayStationWorkerAuthenticator.php';
require_once __DIR__ . '/../Admin/Worker.php';
require_once __DIR__ . '/WorkerScanCoordinator.php';

/**
 * Loads a worker row from the setting table and authenticates with infinite retry.
 *
 * Extracted from ThirtyMinuteCronJob so login backoff and release behavior can be
 * tested without running the full player scan loop.
 */
final class CronWorkerLoginSession
{
    public function __construct(
        private readonly PDO $database,
        private readonly PlayStationWorkerAuthenticator $workerAuthenticator,
        private readonly WorkerScanCoordinator $workerScanCoordinator,
        private readonly Psn100Logger $logger,
        private readonly ?\Closure $sleeper = null,
    ) {
    }

    /**
     * @return array{client: object, worker: array<string, mixed>}
     */
    public function authenticate(int $workerId): array
    {
        while (true) {
            $worker = $this->loadWorkerRow($workerId);

            try {
                $workerAccount = new Worker(
                    (int) $worker['id'],
                    (string) ($worker['refresh_token'] ?? ''),
                    (string) ($worker['npsso'] ?? ''),
                    '',
                    new DateTimeImmutable('1970-01-01 00:00:00'),
                    null,
                );
                $client = $this->workerAuthenticator->authenticateWorker($workerAccount);

                return [
                    'client' => $client,
                    'worker' => $worker,
                ];
            } catch (TypeError) {
                $this->workerScanCoordinator->setWaitingScanProgress(
                    (int) $worker['id'],
                    'Encountered a login problem. Waiting 1 minute before retrying.'
                );
                $this->pause(60);
            } catch (Exception $exception) {
                $this->logger->log("Can't login with worker " . $worker['id']);

                $this->workerScanCoordinator->releaseWorkerFromCurrentScan((int) $worker['id']);

                $this->pause(60 * 30);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadWorkerRow(int $workerId): array
    {
        $query = $this->database->prepare(
            'SELECT
                id,
                refresh_token,
                npsso,
                scanning
            FROM
                setting
            WHERE
                id = :id'
        );
        $query->bindValue(':id', $workerId, PDO::PARAM_INT);
        $query->execute();
        $worker = $query->fetch(PDO::FETCH_ASSOC);

        if ($worker === false) {
            $message = sprintf(
                'Worker %d not found in setting table',
                $workerId
            );
            $this->logger->log($message);

            throw new RuntimeException($message);
        }

        return $worker;
    }

    private function pause(int $seconds): void
    {
        if ($this->sleeper !== null) {
            ($this->sleeper)($seconds);

            return;
        }

        sleep($seconds);
    }
}
