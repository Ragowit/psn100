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
            $trophyResponse = $this->executeLookupRequest(
                $client,
                sprintf(
                    'https://m.np.playstation.com/api/trophy/v1/npCommunicationIds/%s/trophyGroups/all/trophies',
                    rawurlencode($normalizedNpCommunicationId)
                ),
                $preferredNpServiceName
            );
            $normalizedResponse = $this->normalizeResponse($trophyResponse);

            $trophyGroupResponse = $this->executeLookupRequest(
                $client,
                sprintf(
                    'https://m.np.playstation.com/api/trophy/v1/npCommunicationIds/%s/trophyGroups',
                    rawurlencode($normalizedNpCommunicationId)
                ),
                $preferredNpServiceName
            );
            $normalizedGroupResponse = $this->normalizeResponse($trophyGroupResponse);
        } catch (Throwable $exception) {
            $statusCode = $this->determineStatusCode($exception);

            throw new PsnGameLookupException(
                'Failed to retrieve trophy data from PlayStation Network. Please try again later.',
                $statusCode,
                $exception
            );
        }

        $groupedTrophies = $this->groupTrophiesByGroupId($normalizedResponse['trophies'] ?? null);
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

    private function executeLookupRequest(object $client, string $path, ?string $preferredNpServiceName = null): mixed
    {
        if (!method_exists($client, 'get')) {
            throw new RuntimeException('The PlayStation client does not support trophy requests.');
        }

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

        $lastException = null;

        foreach ($queryVariants as $query) {
            try {
                return $client->get($path, $query, ['content-type' => 'application/json']);
            } catch (Throwable $exception) {
                $lastException = $exception;

                if (!$this->shouldRetryWithDifferentServiceName($exception)) {
                    throw $exception;
                }
            }
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        throw new RuntimeException('Unable to retrieve trophy data from PlayStation Network.');
    }

    private function shouldRetryWithDifferentServiceName(Throwable $exception): bool
    {
        $statusCode = $this->determineStatusCode($exception);

        if ($statusCode === 400 || $statusCode === 403 || $statusCode === 404) {
            return true;
        }

        if ($statusCode !== null) {
            return false;
        }

        return $this->isRetryableKnownHttpException($exception);
    }

    private function isRetryableKnownHttpException(Throwable $exception): bool
    {
        $retryableExceptionClasses = [
            'Tustin\\Haste\\Exception\\ApiException',
            'Tustin\\Haste\\Exception\\AccessDeniedHttpException',
            'Tustin\\Haste\\Exception\\NotFoundHttpException',
        ];

        foreach ($retryableExceptionClasses as $retryableExceptionClass) {
            if ($exception instanceof $retryableExceptionClass) {
                return true;
            }
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof Throwable) {
            return $this->isRetryableKnownHttpException($previous);
        }

        return false;
    }

    private function determineStatusCode(Throwable $exception): ?int
    {
        $response = $this->findResponse($exception);

        if ($response !== null) {
            $statusCode = $this->extractStatusCodeFromResponse($response);

            if ($statusCode !== null) {
                return $statusCode;
            }
        }

        return $this->extractStatusCodeFromThrowable($exception);
    }

    private function findResponse(Throwable $exception): ?object
    {
        if (method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();

            if (is_object($response)) {
                return $response;
            }
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof Throwable) {
            return $this->findResponse($previous);
        }

        return null;
    }

    private function extractStatusCodeFromResponse(object $response): ?int
    {
        if (method_exists($response, 'getStatusCode')) {
            $statusCode = $response->getStatusCode();

            if (is_int($statusCode)) {
                return $statusCode;
            }
        }

        if (method_exists($response, 'getStatus')) {
            $status = $response->getStatus();

            if (is_int($status)) {
                return $status;
            }
        }

        return null;
    }

    private function extractStatusCodeFromThrowable(Throwable $exception): ?int
    {
        $code = $exception->getCode();

        if (is_int($code) && $code > 0) {
            return $code;
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof Throwable) {
            return $this->extractStatusCodeFromThrowable($previous);
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
}
