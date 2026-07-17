<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/PlayStationWorkerAuthenticator.php';
require_once __DIR__ . '/PsnGameLookupException.php';
require_once __DIR__ . '/PsnTrophyApiPayloadInspector.php';
require_once __DIR__ . '/PsnTrophyGroupAssembler.php';
require_once __DIR__ . '/../PsnHttpExceptionClassifier.php';
require_once __DIR__ . '/../CommaSeparatedValues.php';

use Tustin\PlayStation\Client;

final class PsnGameLookupService
{
    private readonly PlayStationWorkerAuthenticator $workerAuthenticator;
    private readonly PsnTrophyApiPayloadInspector $payloadInspector;
    private readonly PsnTrophyGroupAssembler $trophyGroupAssembler;

    public function __construct(
        private readonly PDO $database,
        callable $workerFetcher,
        ?callable $clientFactory = null,
        ?callable $refreshTokenSaver = null,
        ?PlayStationWorkerAuthenticator $workerAuthenticator = null,
        ?PsnTrophyApiPayloadInspector $payloadInspector = null,
        ?PsnTrophyGroupAssembler $trophyGroupAssembler = null,
    ) {
        $this->workerAuthenticator = $workerAuthenticator ?? new PlayStationWorkerAuthenticator(
            $workerFetcher,
            $clientFactory,
            $refreshTokenSaver,
        );
        $this->payloadInspector = $payloadInspector ?? new PsnTrophyApiPayloadInspector();
        $this->trophyGroupAssembler = $trophyGroupAssembler ?? new PsnTrophyGroupAssembler();
    }

    public static function fromDatabase(PDO $database): self
    {
        $workerService = new WorkerService($database);

        return new self(
            $database,
            static fn (): array => $workerService->fetchWorkers(),
            null,
            static fn (int $workerId, string $refreshToken): bool => $workerService->updateWorkerRefreshToken($workerId, $refreshToken),
            PlayStationWorkerAuthenticator::fromWorkerService($workerService),
        );
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

        return [
            'game' => [
                'id' => $gameMetadata['id'],
                'name' => $gameMetadata['name'],
                'npCommunicationId' => $gameMetadata['np_communication_id'],
            ],
            'trophyData' => $this->fetchTrophyDataForNpCommunicationId($gameMetadata['np_communication_id']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchTrophyDataForNpCommunicationId(string $npCommunicationId, ?object $authenticatedClient = null): array
    {
        $normalizedNpCommunicationId = trim($npCommunicationId);
        if ($normalizedNpCommunicationId === '') {
            throw new InvalidArgumentException('NP communication ID must be provided.');
        }

        $client = $authenticatedClient ?? $this->createAuthenticatedClient();
        $preferredNpServiceName = $this->resolvePreferredNpServiceName($normalizedNpCommunicationId);

        try {
            $trophyRequestResult = $this->executeLookupRequest(
                $client,
                sprintf(
                    'https://m.np.playstation.com/api/trophy/v1/npCommunicationIds/%s/trophyGroups/all/trophies',
                    rawurlencode($normalizedNpCommunicationId)
                ),
                $preferredNpServiceName
            );
            $normalizedResponse = $this->payloadInspector->normalize($trophyRequestResult['payload']);
            $this->payloadInspector->assertMatchesRequested(
                $normalizedNpCommunicationId,
                $normalizedResponse,
                'all/trophies'
            );

            $trophyGroupsPath = sprintf(
                'https://m.np.playstation.com/api/trophy/v1/npCommunicationIds/%s/trophyGroups',
                rawurlencode($normalizedNpCommunicationId)
            );

            try {
                $trophyGroupRequestResult = $this->executeLookupRequest(
                    $client,
                    $trophyGroupsPath,
                    null,
                    $trophyRequestResult['query']
                );
                $normalizedGroupResponse = $this->payloadInspector->normalize($trophyGroupRequestResult['payload']);
                $this->payloadInspector->assertMatchesRequested(
                    $normalizedNpCommunicationId,
                    $normalizedGroupResponse,
                    'trophyGroups'
                );
            } catch (Throwable $trophyGroupsException) {
                if (!PsnHttpExceptionClassifier::shouldRetryWithDifferentServiceName($trophyGroupsException)) {
                    throw $trophyGroupsException;
                }

                $fallbackQuery = $this->resolveAlternateQueryVariant(
                    $this->buildLookupQueryVariants($preferredNpServiceName),
                    $trophyRequestResult['query']
                );

                if ($fallbackQuery === null) {
                    throw $trophyGroupsException;
                }

                $trophyRequestResult = $this->executeLookupRequest(
                    $client,
                    sprintf(
                        'https://m.np.playstation.com/api/trophy/v1/npCommunicationIds/%s/trophyGroups/all/trophies',
                        rawurlencode($normalizedNpCommunicationId)
                    ),
                    null,
                    $fallbackQuery
                );
                $normalizedResponse = $this->payloadInspector->normalize($trophyRequestResult['payload']);
                $this->payloadInspector->assertMatchesRequested(
                    $normalizedNpCommunicationId,
                    $normalizedResponse,
                    'all/trophies'
                );

                $trophyGroupRequestResult = $this->executeLookupRequest(
                    $client,
                    $trophyGroupsPath,
                    null,
                    $fallbackQuery
                );
                $normalizedGroupResponse = $this->payloadInspector->normalize($trophyGroupRequestResult['payload']);
                $this->payloadInspector->assertMatchesRequested(
                    $normalizedNpCommunicationId,
                    $normalizedGroupResponse,
                    'trophyGroups'
                );
            }
        } catch (Throwable $exception) {
            if ($exception instanceof PsnGameLookupException) {
                throw $exception;
            }

            $statusCode = PsnHttpExceptionClassifier::determineStatusCode($exception);

            throw new PsnGameLookupException(
                'Failed to retrieve trophy data from PlayStation Network. Please try again later.',
                $statusCode,
                $exception
            );
        }

        $normalizedResponse['trophyGroups'] = $this->trophyGroupAssembler->assemble(
            $normalizedResponse['trophies'] ?? null,
            $normalizedResponse['trophyGroups'] ?? null,
            $normalizedGroupResponse['trophyGroups'] ?? null,
        );

        return $normalizedResponse;
    }

    private function resolvePreferredNpServiceName(string $npCommunicationId): ?string
    {
        $query = $this->database->prepare(
            'SELECT platform FROM trophy_title WHERE np_communication_id = :np_communication_id LIMIT 1'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $platform = $query->fetchColumn();
        if ($platform === false) {
            return null;
        }

        $platforms = CommaSeparatedValues::parseUppercaseTrimmed((string) $platform);

        if ($platforms === []) {
            return null;
        }

        $legacyPlatforms = ['PS3', 'PS4', 'PSVR', 'PSVITA'];
        foreach ($platforms as $platformValue) {
            if (in_array($platformValue, $legacyPlatforms, true)) {
                return 'trophy';
            }
        }

        return 'trophy2';
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
        return $this->workerAuthenticator->authenticateWithNextAvailableWorker();
    }

    /**
     * @param array{npLanguage: string, npServiceName?: string}|null $pinnedQuery
     * @return array{payload: mixed, query: array{npLanguage: string, npServiceName?: string}}
     */
    private function executeLookupRequest(
        object $client,
        string $path,
        ?string $preferredNpServiceName = null,
        ?array $pinnedQuery = null
    ): array
    {
        if (!method_exists($client, 'get')) {
            throw new RuntimeException('The PlayStation client does not support trophy requests.');
        }

        $queryVariants = $pinnedQuery === null
            ? $this->buildLookupQueryVariants($preferredNpServiceName)
            : [$pinnedQuery];

        $lastException = null;

        foreach ($queryVariants as $query) {
            try {
                return [
                    'payload' => $client->get($path, $query, ['content-type' => 'application/json']),
                    'query' => $query,
                ];
            } catch (Throwable $exception) {
                $lastException = $exception;

                if ($pinnedQuery !== null || !PsnHttpExceptionClassifier::shouldRetryWithDifferentServiceName($exception)) {
                    throw $exception;
                }
            }
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        throw new RuntimeException('Unable to retrieve trophy data from PlayStation Network.');
    }

    /**
     * @return list<array{npLanguage: string, npServiceName?: string}>
     */
    private function buildLookupQueryVariants(?string $preferredNpServiceName): array
    {
        $queryVariants = [];
        $addVariant = static function (array $variant) use (&$queryVariants): void {
            if (!in_array($variant, $queryVariants, true)) {
                $queryVariants[] = $variant;
            }
        };

        if ($preferredNpServiceName === 'trophy' || $preferredNpServiceName === 'trophy2') {
            $addVariant(['npLanguage' => 'en-US', 'npServiceName' => $preferredNpServiceName]);
        } else {
            $addVariant(['npLanguage' => 'en-US']);
        }

        $addVariant(['npLanguage' => 'en-US', 'npServiceName' => 'trophy']);
        $addVariant(['npLanguage' => 'en-US', 'npServiceName' => 'trophy2']);
        $addVariant(['npLanguage' => 'en-US']);

        return $queryVariants;
    }

    /**
     * @param list<array{npLanguage: string, npServiceName?: string}> $queryVariants
     * @param array{npLanguage: string, npServiceName?: string} $winningQuery
     * @return array{npLanguage: string, npServiceName?: string}|null
     */
    private function resolveAlternateQueryVariant(array $queryVariants, array $winningQuery): ?array
    {
        foreach ($queryVariants as $queryVariant) {
            if ($queryVariant !== $winningQuery) {
                return $queryVariant;
            }
        }

        return null;
    }

}
