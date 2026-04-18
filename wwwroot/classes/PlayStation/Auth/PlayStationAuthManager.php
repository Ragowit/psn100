<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/PlayStationApiClientInterface.php';
require_once __DIR__ . '/../Contracts/PlayStationClientFactoryInterface.php';
require_once __DIR__ . '/../PlayStationClientFactory.php';

final class PlayStationAuthManager
{
    /**
     * @var array<int, int>
     */
    private array $workerBlockedUntil = [];

    private readonly PlayStationClientFactoryInterface $playStationClientFactory;

    /**
     * @var null|\Closure(string): void
     */
    private readonly ?\Closure $logListener;

    public function __construct(
        private readonly PDO $database,
        PlayStationClientFactoryInterface|null $playStationClientFactory = null,
        ?callable $logListener = null
    ) {
        $this->playStationClientFactory = $playStationClientFactory ?? new PlayStationClientFactory();
        $this->logListener = $logListener === null ? null : \Closure::fromCallable($logListener);
    }

    /**
     * Authenticates using NPSSO values from the setting table.
     *
     * Workers are attempted in ascending id order to preserve existing failover behavior.
     *
     * @return array{worker_id: int, client: PlayStationApiClientInterface}
     */
    public function authenticateWorker(int $retryDelaySeconds = 3): array
    {
        $retryDelaySeconds = $this->normalizeSleepDelay($retryDelaySeconds);

        while (true) {
            $workers = $this->fetchWorkersInFailoverOrder();
            $availableWorkers = $this->filterWorkersByCooldown($workers);

            if ($availableWorkers === []) {
                $sleepSeconds = $workers === []
                    ? $retryDelaySeconds
                    : max(1, $this->secondsUntilNextWorkerAvailable());
                $this->logAuthResult(0, sprintf('no workers available; sleeping %d second(s)', $sleepSeconds), false);
                sleep($sleepSeconds);
                continue;
            }

            foreach ($availableWorkers as $worker) {
                $authenticatedWorker = $this->authenticateWorkerRecord($worker);
                if ($authenticatedWorker !== null) {
                    return $authenticatedWorker;
                }
            }

            $this->logAuthResult(0, sprintf('all workers failed; sleeping %d second(s)', $retryDelaySeconds), false);
            sleep($retryDelaySeconds);
        }
    }

    /**
     * Authenticates a specific worker by id.
     *
     * @return array{worker_id: int, client: PlayStationApiClientInterface}
     */
    public function authenticateSpecificWorker(int $workerId): array
    {
        $worker = $this->fetchWorkerById($workerId);
        if ($worker === null) {
            throw new RuntimeException(sprintf('Worker %d not found in setting table.', $workerId));
        }

        if (($this->workerBlockedUntil[$workerId] ?? 0) > time()) {
            throw new RuntimeException(sprintf('Worker %d is currently in cooldown.', $workerId));
        }

        $authenticatedWorker = $this->authenticateWorkerRecord($worker);
        if ($authenticatedWorker === null) {
            throw new RuntimeException(sprintf('Unable to authenticate worker %d.', $workerId));
        }

        return $authenticatedWorker;
    }

    /**
     * Refreshes a worker session and returns whether the token appears usable.
     */
    public function refreshSession(int $workerId, PlayStationApiClientInterface $client): bool
    {
        try {
            $client->refreshAccessToken();
            $accessToken = $client->acquireAccessToken();

            $success = is_string($accessToken) && $accessToken !== '';
            $this->logAuthResult($workerId, $success ? 'session refresh succeeded' : 'session refresh returned empty token', $success);

            return $success;
        } catch (Throwable $throwable) {
            $this->logAuthResult(
                $workerId,
                sprintf('session refresh failed: %s', $throwable::class),
                false
            );

            return false;
        }
    }

    /**
     * Marks a worker as unavailable and rotates authentication attempts to the next worker.
     */
    public function invalidateAndRotateWorkerOnAuthFailure(int $workerId, int $cooldownSeconds = 1800): void
    {
        $blockedUntil = time() + max(1, $cooldownSeconds);
        $this->workerBlockedUntil[$workerId] = $blockedUntil;

        $this->logAuthResult(
            $workerId,
            sprintf('worker invalidated for %d second(s)', max(1, $cooldownSeconds)),
            false
        );
    }

    /**
     * @return list<array{id: int|string, npsso: string|null}>
     */
    private function fetchWorkersInFailoverOrder(): array
    {
        $query = $this->database->prepare('SELECT id, npsso FROM setting ORDER BY id');
        $query->execute();

        /** @var list<array{id: int|string, npsso: string|null}> $workers */
        $workers = $query->fetchAll(PDO::FETCH_ASSOC);

        return $workers;
    }

    /**
     * @return array{id: int|string, npsso: string|null}|null
     */
    private function fetchWorkerById(int $workerId): ?array
    {
        $query = $this->database->prepare('SELECT id, npsso FROM setting WHERE id = :worker_id LIMIT 1');
        $query->bindValue(':worker_id', $workerId, PDO::PARAM_INT);
        $query->execute();

        $worker = $query->fetch(PDO::FETCH_ASSOC);

        if (!is_array($worker)) {
            return null;
        }

        return $worker;
    }

    /**
     * @param list<array{id: int|string, npsso: string|null}> $workers
     *
     * @return list<array{id: int|string, npsso: string|null}>
     */
    private function filterWorkersByCooldown(array $workers): array
    {
        $now = time();

        return array_values(array_filter(
            $workers,
            fn (array $worker): bool => ($this->workerBlockedUntil[(int) $worker['id']] ?? 0) <= $now
        ));
    }

    private function secondsUntilNextWorkerAvailable(): int
    {
        if ($this->workerBlockedUntil === []) {
            return 1;
        }

        $now = time();
        $nextAvailableUnix = min($this->workerBlockedUntil);

        return max(1, $nextAvailableUnix - $now);
    }

    private function logAuthResult(int $workerId, string $result, bool $success): void
    {
        $message = sprintf(
            '[auth] worker=%d result=%s status=%s',
            $workerId,
            $result,
            $success ? 'success' : 'failure'
        );

        if ($this->logListener !== null) {
            ($this->logListener)($message);

            return;
        }

        $query = $this->database->prepare('INSERT INTO log(message) VALUES(:message)');
        $query->bindValue(':message', $message, PDO::PARAM_STR);
        $query->execute();
    }

    private function normalizeSleepDelay(int $delaySeconds): int
    {
        return max(1, $delaySeconds);
    }

    /**
     * @param array{id: int|string, npsso: string|null} $worker
     *
     * @return array{worker_id: int, client: PlayStationApiClientInterface}|null
     */
    private function authenticateWorkerRecord(array $worker): ?array
    {
        $workerId = (int) $worker['id'];
        $npsso = trim((string) ($worker['npsso'] ?? ''));

        if ($npsso === '') {
            $this->logAuthResult($workerId, 'empty NPSSO; skipping', false);

            return null;
        }

        try {
            $client = $this->playStationClientFactory->createClient();
            $client->loginWithNpsso($npsso);
            $this->workerBlockedUntil[$workerId] = 0;
            $this->logAuthResult($workerId, 'authenticated', true);

            return [
                'worker_id' => $workerId,
                'client' => $client,
            ];
        } catch (Throwable $throwable) {
            $this->logAuthResult(
                $workerId,
                sprintf('authentication failed: %s', $throwable::class),
                false
            );

            return null;
        }
    }
}
