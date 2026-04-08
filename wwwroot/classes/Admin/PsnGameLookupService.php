<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/PsnGameLookupException.php';

use Tustin\PlayStation\Client;

final class PsnGameLookupService
{
    /**
     * @var \Closure(): iterable<Worker>
     */
    private readonly \Closure $workerFetcher;

    /**
     * @var \Closure(): object
     */
    private readonly \Closure $clientFactory;

    public function __construct(
        private readonly PDO $database,
        callable $workerFetcher,
        ?callable $clientFactory = null
    ) {
        $this->workerFetcher = \Closure::fromCallable($workerFetcher);
        $this->clientFactory = \Closure::fromCallable(
            $clientFactory ?? static fn (): object => new Client()
        );
    }

    public static function fromDatabase(PDO $database): self
    {
        $workerService = new WorkerService($database);

        return new self($database, static fn (): array => $workerService->fetchWorkers());
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupByGameId(string $gameId): array
    {
        $normalizedGameId = trim($gameId);

        if ($normalizedGameId === '' || !ctype_digit($normalizedGameId)) {
            throw new InvalidArgumentException('Game ID must be a numeric value.');
        }

        $gameMetadata = $this->fetchGameMetadata((int) $normalizedGameId);

        if ($gameMetadata === null) {
            throw new PsnGameLookupException(sprintf('Game ID "%s" was not found.', $normalizedGameId));
        }

        $client = $this->createAuthenticatedClient();

        try {
            $trophyResponse = $this->executeGameTrophyRequest($client, $gameMetadata['np_communication_id']);
        } catch (Throwable $exception) {
            $statusCode = $this->determineStatusCode($exception);

            throw new PsnGameLookupException(
                'Failed to retrieve trophy data from PlayStation Network. Please try again later.',
                $statusCode,
                $exception
            );
        }

        return [
            'game' => [
                'id' => $gameMetadata['id'],
                'name' => $gameMetadata['name'],
                'npCommunicationId' => $gameMetadata['np_communication_id'],
            ],
            'trophyData' => $this->normalizeResponse($trophyResponse),
        ];
    }

    /**
     * @return array{id: int, np_communication_id: string, name: string}|null
     */
    private function fetchGameMetadata(int $gameId): ?array
    {
        $query = $this->database->prepare(
            'SELECT id, np_communication_id, name FROM trophy_title WHERE id = :id LIMIT 1'
        );
        $query->bindValue(':id', $gameId, PDO::PARAM_INT);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'np_communication_id' => (string) $row['np_communication_id'],
            'name' => (string) $row['name'],
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

                if (!method_exists($client, 'loginWithNpsso')) {
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

    private function executeGameTrophyRequest(object $client, string $npCommunicationId): mixed
    {
        if (!method_exists($client, 'get')) {
            throw new RuntimeException('The PlayStation client does not support trophy requests.');
        }

        $path = sprintf(
            'https://m.np.playstation.com/api/trophy/v1/npCommunicationIds/%s/trophyGroups/all/trophies',
            rawurlencode($npCommunicationId)
        );

        return $client->get($path, ['npLanguage' => 'en-US'], ['content-type' => 'application/json']);
    }

    private function determineStatusCode(Throwable $exception): ?int
    {
        if (method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();

            if (is_object($response) && method_exists($response, 'getStatusCode')) {
                $statusCode = $response->getStatusCode();

                if (is_int($statusCode)) {
                    return $statusCode;
                }
            }
        }

        $code = $exception->getCode();

        return is_int($code) && $code > 0 ? $code : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeResponse(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_object($response)) {
            try {
                $encoded = json_encode($response, JSON_THROW_ON_ERROR);
                $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (JsonException) {
            }

            return get_object_vars($response);
        }

        return [];
    }
}
