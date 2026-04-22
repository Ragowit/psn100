<?php

declare(strict_types=1);

require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/PsnTrophyTitleComparisonException.php';

use Tustin\PlayStation\Client;

final class PsnTrophyTitleComparisonService
{
    /**
     * @var \Closure(): iterable<Worker>
     */
    private readonly \Closure $workerFetcher;

    /**
     * @var \Closure(): object
     */
    private readonly \Closure $clientFactory;

    /**
     * @var \Closure(): float
     */
    private readonly \Closure $timeProvider;

    /**
     * @param callable(): iterable<Worker> $workerFetcher
     * @param callable(): object|null $clientFactory
     * @param callable(): float|null $timeProvider
     */
    public function __construct(
        callable $workerFetcher,
        ?callable $clientFactory = null,
        ?callable $timeProvider = null,
    ) {
        $this->workerFetcher = \Closure::fromCallable($workerFetcher);
        $this->clientFactory = \Closure::fromCallable($clientFactory ?? static fn (): object => new Client());
        $this->timeProvider = \Closure::fromCallable($timeProvider ?? static fn (): float => microtime(true));
    }

    public static function fromDatabase(PDO $database): self
    {
        $workerService = new WorkerService($database);

        return new self(static fn (): array => $workerService->fetchWorkers());
    }

    /**
     * @return array<string, mixed>
     */
    public function compareByAccountId(string $accountId): array
    {
        $normalizedAccountId = trim($accountId);

        if ($normalizedAccountId === '' || !ctype_digit($normalizedAccountId)) {
            throw new InvalidArgumentException('Account ID must be a numeric value.');
        }

        $client = $this->createAuthenticatedClient();

        $directFetch = $this->fetchTitlesViaEndpoint($client, $normalizedAccountId);
        $tustinFetch = $this->fetchTitlesViaTustin($client, $normalizedAccountId);

        return [
            'accountId' => $normalizedAccountId,
            'direct' => $directFetch,
            'tustin' => $tustinFetch,
            'countsMatch' => ($directFetch['count'] ?? -1) === ($tustinFetch['count'] ?? -1),
        ];
    }

    private function createAuthenticatedClient(): object
    {
        $factory = $this->clientFactory;

        foreach (($this->workerFetcher)() as $worker) {
            if (!$worker instanceof Worker) {
                continue;
            }

            $npsso = $worker->getNpsso();
            if ($npsso === '') {
                continue;
            }

            try {
                $client = $factory();

                if (!is_object($client) || !method_exists($client, 'loginWithNpsso')) {
                    throw new RuntimeException('The PlayStation client does not support NPSSO authentication.');
                }

                $client->loginWithNpsso($npsso);

                return $client;
            } catch (Throwable) {
                continue;
            }
        }

        throw new RuntimeException('Unable to login to any worker accounts.');
    }

    /**
     * @return array{count: int, durationMs: float, pagesFetched: int, totalItemCount: int|null, titles: array<int, array<string, mixed>>}
     */
    private function fetchTitlesViaEndpoint(object $client, string $accountId): array
    {
        if (!method_exists($client, 'get')) {
            throw new PsnTrophyTitleComparisonException('The PlayStation client does not support endpoint requests.');
        }

        $limit = 800;
        $offset = 0;
        $totalItemCount = null;
        $titles = [];
        $pagesFetched = 0;
        $startTime = ($this->timeProvider)();

        while (true) {
            $path = sprintf(
                'https://m.np.playstation.com/api/trophy/v1/users/%s/trophyTitles',
                rawurlencode($accountId)
            );

            try {
                $payload = $client->get(
                    $path,
                    [
                        'limit' => $limit,
                        'offset' => $offset,
                    ],
                    ['content-type' => 'application/json']
                );
            } catch (Throwable $exception) {
                throw new PsnTrophyTitleComparisonException(
                    'Failed to retrieve trophy titles from the direct endpoint.',
                    $this->determineStatusCode($exception),
                    $exception
                );
            }

            $normalizedPayload = $this->normalizeResponse($payload);
            $batchTitles = $normalizedPayload['trophyTitles'] ?? [];

            if (!is_array($batchTitles)) {
                $batchTitles = [];
            }

            foreach ($batchTitles as $batchTitle) {
                if (is_array($batchTitle)) {
                    $titles[] = $batchTitle;
                }
            }

            $pagesFetched++;

            if (isset($normalizedPayload['totalItemCount']) && is_numeric($normalizedPayload['totalItemCount'])) {
                $totalItemCount = (int) $normalizedPayload['totalItemCount'];
            }

            $batchCount = count($batchTitles);
            if ($batchCount === 0) {
                break;
            }

            $offset += $batchCount;

            if ($totalItemCount !== null) {
                if ($offset >= $totalItemCount) {
                    break;
                }

                continue;
            }

            if ($batchCount < $limit) {
                break;
            }
        }

        $endTime = ($this->timeProvider)();

        return [
            'count' => count($titles),
            'durationMs' => round(($endTime - $startTime) * 1000, 2),
            'pagesFetched' => $pagesFetched,
            'totalItemCount' => $totalItemCount,
            'titles' => $titles,
        ];
    }

    /**
     * @return array{count: int, durationMs: float, titles: array<int, mixed>}
     */
    private function fetchTitlesViaTustin(object $client, string $accountId): array
    {
        if (!method_exists($client, 'users')) {
            throw new PsnTrophyTitleComparisonException('The PlayStation client does not support user requests.');
        }

        try {
            $users = $client->users();

            if (!is_object($users) || !method_exists($users, 'find')) {
                throw new PsnTrophyTitleComparisonException('The PlayStation client does not support user requests.');
            }

            $user = $users->find($accountId);
            $startTime = ($this->timeProvider)();
            $trophyTitleCollection = $user->trophyTitles();
            $trophyTitles = iterator_to_array($trophyTitleCollection->getIterator());
        } catch (Throwable $exception) {
            throw new PsnTrophyTitleComparisonException(
                'Failed to retrieve trophy titles via tustin/psn-php.',
                $this->determineStatusCode($exception),
                $exception
            );
        }

        $endTime = ($this->timeProvider)();

        return [
            'count' => count($trophyTitles),
            'durationMs' => round(($endTime - $startTime) * 1000, 2),
            'titles' => $trophyTitles,
        ];
    }

    private function determineStatusCode(Throwable $exception): ?int
    {
        $code = $exception->getCode();

        if (is_int($code) && $code > 0) {
            return $code;
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof Throwable) {
            return $this->determineStatusCode($previous);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeResponse(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_object($payload)) {
            try {
                $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
                $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (JsonException) {
            }

            return get_object_vars($payload);
        }

        return [];
    }
}
