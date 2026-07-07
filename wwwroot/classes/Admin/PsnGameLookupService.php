<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/PlayStationWorkerAuthenticator.php';
require_once __DIR__ . '/PsnGameLookupException.php';
require_once __DIR__ . '/../PsnHttpExceptionClassifier.php';

use Tustin\PlayStation\Client;

final class PsnGameLookupService
{
    private readonly PlayStationWorkerAuthenticator $workerAuthenticator;

    public function __construct(
        private readonly PDO $database,
        callable $workerFetcher,
        ?callable $clientFactory = null,
        ?callable $refreshTokenSaver = null,
        ?PlayStationWorkerAuthenticator $workerAuthenticator = null,
    ) {
        $this->workerAuthenticator = $workerAuthenticator ?? new PlayStationWorkerAuthenticator(
            $workerFetcher,
            $clientFactory,
            $refreshTokenSaver,
        );
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
            $normalizedResponse = $this->normalizeResponse($trophyRequestResult['payload']);
            $this->assertPayloadMatchesRequestedNpCommunicationId(
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
                $normalizedGroupResponse = $this->normalizeResponse($trophyGroupRequestResult['payload']);
                $this->assertPayloadMatchesRequestedNpCommunicationId(
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
                $normalizedResponse = $this->normalizeResponse($trophyRequestResult['payload']);
                $this->assertPayloadMatchesRequestedNpCommunicationId(
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
                $normalizedGroupResponse = $this->normalizeResponse($trophyGroupRequestResult['payload']);
                $this->assertPayloadMatchesRequestedNpCommunicationId(
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

        $groupedTrophies = $this->groupTrophiesByGroupId($normalizedResponse['trophies'] ?? null);
        if ($groupedTrophies === []) {
            $groupedTrophies = $this->groupNestedTrophiesFromGroups($normalizedResponse['trophyGroups'] ?? null);
        }
        $normalizedResponse['trophyGroups'] = $this->buildTrophyGroups(
            $normalizedGroupResponse['trophyGroups'] ?? null,
            $groupedTrophies
        );

        return $normalizedResponse;
    }

    /**
     * @param mixed $rawGroups
     * @param array<int, array{trophyGroupId: string, trophyGroupName: string, trophyGroupDetail: string, trophyGroupIconUrl: string, trophies: array<int, array<string, mixed>>}> $groupedTrophies
     * @return array<int, array{trophyGroupId: string, trophyGroupName: string, trophyGroupDetail: string, trophyGroupIconUrl: string, trophies: array<int, array<string, mixed>>}>
     */
    private function buildTrophyGroups(mixed $rawGroups, array $groupedTrophies): array
    {
        $trophiesByGroupId = [];
        foreach ($groupedTrophies as $groupedTrophy) {
            $groupId = $groupedTrophy['trophyGroupId'] ?? '';
            if (!is_string($groupId) || $groupId === '') {
                continue;
            }

            $trophiesByGroupId[$groupId] = $groupedTrophy['trophies'] ?? [];
        }

        if (!is_array($rawGroups)) {
            return $groupedTrophies;
        }

        $groups = [];

        foreach ($rawGroups as $rawGroup) {
            if (!is_array($rawGroup)) {
                continue;
            }

            $groupId = (string) ($rawGroup['trophyGroupId'] ?? '');
            if ($groupId === '') {
                continue;
            }

            $groups[] = [
                'trophyGroupId' => $groupId,
                'trophyGroupName' => (string) ($rawGroup['trophyGroupName'] ?? ''),
                'trophyGroupDetail' => (string) ($rawGroup['trophyGroupDetail'] ?? ''),
                'trophyGroupIconUrl' => (string) ($rawGroup['trophyGroupIconUrl'] ?? ''),
                'trophies' => is_array($trophiesByGroupId[$groupId] ?? null) ? $trophiesByGroupId[$groupId] : [],
            ];
        }

        if ($groups === []) {
            return $groupedTrophies;
        }

        return $groups;
    }

    /**
     * @return array<int, array{trophyGroupId: string, trophyGroupName: string, trophyGroupDetail: string, trophyGroupIconUrl: string, trophies: array<int, array<string, mixed>>}>
     */
    private function groupTrophiesByGroupId(mixed $rawTrophies): array
    {
        if (!is_array($rawTrophies)) {
            return [];
        }

        $groups = [];

        foreach ($rawTrophies as $rawTrophy) {
            if (!is_array($rawTrophy)) {
                continue;
            }

            $groupId = (string) ($rawTrophy['trophyGroupId'] ?? '');
            if ($groupId === '') {
                continue;
            }

            if (!isset($groups[$groupId])) {
                $groups[$groupId] = [
                    'trophyGroupId' => $groupId,
                    'trophyGroupName' => (string) ($rawTrophy['trophyGroupName'] ?? ''),
                    'trophyGroupDetail' => (string) ($rawTrophy['trophyGroupDetail'] ?? ''),
                    'trophyGroupIconUrl' => (string) ($rawTrophy['trophyGroupIconUrl'] ?? ''),
                    'trophies' => [],
                ];
            }

            $groups[$groupId]['trophies'][] = $rawTrophy;
        }

        return array_values($groups);
    }

    /**
     * @return array<int, array{trophyGroupId: string, trophyGroupName: string, trophyGroupDetail: string, trophyGroupIconUrl: string, trophies: array<int, array<string, mixed>>}>
     */
    private function groupNestedTrophiesFromGroups(mixed $rawGroups): array
    {
        if (!is_array($rawGroups)) {
            return [];
        }

        $groups = [];

        foreach ($rawGroups as $rawGroup) {
            if (!is_array($rawGroup)) {
                continue;
            }

            $groupId = (string) ($rawGroup['trophyGroupId'] ?? '');
            if ($groupId === '') {
                continue;
            }

            $trophies = $rawGroup['trophies'] ?? null;
            if (!is_array($trophies)) {
                $trophies = [];
            }

            $groups[] = [
                'trophyGroupId' => $groupId,
                'trophyGroupName' => (string) ($rawGroup['trophyGroupName'] ?? ''),
                'trophyGroupDetail' => (string) ($rawGroup['trophyGroupDetail'] ?? ''),
                'trophyGroupIconUrl' => (string) ($rawGroup['trophyGroupIconUrl'] ?? ''),
                'trophies' => $trophies,
            ];
        }

        return $groups;
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

        $platforms = array_values(array_filter(array_map(
            static fn (string $value): string => strtoupper(trim($value)),
            explode(',', (string) $platform)
        ), static fn (string $value): bool => $value !== ''));

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

    /**
     * @param array<string, mixed> $payload
     */
    private function extractNpCommunicationIdsFromPayload(array $payload): array
    {
        $detected = [];
        $seen = [];

        $this->addNpCommunicationIdCandidates(
            $this->extractNpCommunicationIdFromArray($payload),
            $detected,
            $seen
        );

        $this->addNpCommunicationIdCandidates(
            $this->extractNpCommunicationIdFromTrophies($payload['trophies'] ?? null),
            $detected,
            $seen
        );

        $trophyGroups = $payload['trophyGroups'] ?? null;
        if (!is_array($trophyGroups)) {
            return $detected;
        }

        foreach ($trophyGroups as $trophyGroup) {
            if (!is_array($trophyGroup)) {
                continue;
            }

            $this->addNpCommunicationIdCandidates(
                $this->extractNpCommunicationIdFromArray($trophyGroup),
                $detected,
                $seen
            );

            $this->addNpCommunicationIdCandidates(
                $this->extractNpCommunicationIdFromTrophies($trophyGroup['trophies'] ?? null),
                $detected,
                $seen
            );
        }

        return $detected;
    }

    private function extractNpCommunicationIdFromTrophies(mixed $trophies): array
    {
        if (!is_array($trophies) || $trophies === []) {
            return [];
        }

        $detected = [];
        $seen = [];

        foreach ($trophies as $trophy) {
            if (!is_array($trophy)) {
                continue;
            }

            $this->addNpCommunicationIdCandidates(
                $this->extractNpCommunicationIdFromArray($trophy),
                $detected,
                $seen
            );

            $trophyIconUrl = $trophy['trophyIconUrl'] ?? null;
            if (!is_string($trophyIconUrl) || trim($trophyIconUrl) === '') {
                continue;
            }

            if (preg_match('/\/(NP[A-Z0-9]{2}[0-9]{5}_[0-9]{2})_/i', $trophyIconUrl, $matches) !== 1) {
                continue;
            }

            $this->addNpCommunicationIdCandidates($matches[1], $detected, $seen);
        }

        return $detected;
    }

    /**
     * @param list<string>|string|null $candidates
     * @param list<string>             $detected
     * @param array<string, true>      $seen
     */
    private function addNpCommunicationIdCandidates(array|string|null $candidates, array &$detected, array &$seen): void
    {
        if ($candidates === null) {
            return;
        }

        if (is_string($candidates)) {
            $candidates = [$candidates];
        }

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $normalizedCandidate = strtoupper(trim($candidate));
            if (isset($seen[$normalizedCandidate])) {
                continue;
            }

            $detected[] = $normalizedCandidate;
            $seen[$normalizedCandidate] = true;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractNpCommunicationIdFromArray(array $payload): ?string
    {
        $topLevelCandidateKeys = ['npCommunicationId', 'np_communication_id'];
        foreach ($topLevelCandidateKeys as $candidateKey) {
            $candidate = $payload[$candidateKey] ?? null;
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertPayloadMatchesRequestedNpCommunicationId(string $requested, array $payload, string $endpointLabel): void
    {
        $detected = $this->extractNpCommunicationIdsFromPayload($payload);
        if ($detected === []) {
            return;
        }

        $normalizedRequested = strtoupper(trim($requested));
        foreach ($detected as $normalizedDetected) {
            if ($normalizedDetected === $normalizedRequested) {
                continue;
            }

            throw new PsnGameLookupException(sprintf(
                'PSN response integrity check failed for endpoint "%s": requested npCommunicationId "%s" but received "%s".',
                $endpointLabel,
                $normalizedRequested,
                $normalizedDetected
            ));
        }
    }
}
