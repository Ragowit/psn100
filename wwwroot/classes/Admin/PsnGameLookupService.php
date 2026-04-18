<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/PsnGameLookupException.php';
require_once __DIR__ . '/../PlayStationClientMode.php';
require_once __DIR__ . '/../PlayStationClientModeConfig.php';
require_once __DIR__ . '/../PlayStation/Contracts/PlayStationApiClientInterface.php';
require_once __DIR__ . '/../PlayStation/Contracts/PlayStationClientFactoryInterface.php';
require_once __DIR__ . '/../PlayStation/Contracts/TrophyClientInterface.php';
require_once __DIR__ . '/../PlayStation/Exception/PlayStationAccessDeniedException.php';
require_once __DIR__ . '/../PlayStation/Exception/PlayStationNotFoundException.php';
require_once __DIR__ . '/../PlayStation/Exception/PlayStationTransientUpstreamException.php';
require_once __DIR__ . '/../PlayStation/Policy/NpServiceNamePolicy.php';
require_once __DIR__ . '/../PlayStation/PlayStationClientFactory.php';

final class PsnGameLookupService
{
    /**
     * @var \Closure(): iterable<Worker>
     */
    private readonly \Closure $workerFetcher;

    private readonly PlayStationClientFactoryInterface $newClientFactory;
    /**
     * @var \Closure(): object
     */
    private readonly \Closure $legacyClientFactory;
    private readonly PlayStationClientMode $clientMode;
    private readonly NpServiceNamePolicy $npServiceNamePolicy;

    public function __construct(
        private readonly PDO $database,
        callable $workerFetcher,
        PlayStationClientFactoryInterface|callable|null $playStationClientFactory = null,
        ?PlayStationClientMode $clientMode = null,
        ?callable $legacyClientFactory = null
    ) {
        $this->workerFetcher = \Closure::fromCallable($workerFetcher);
        $this->newClientFactory = $this->normalizePlayStationClientFactory($playStationClientFactory);
        $this->clientMode = $clientMode ?? PlayStationClientMode::Legacy;
        if ($legacyClientFactory !== null) {
            $this->legacyClientFactory = \Closure::fromCallable($legacyClientFactory);
        } elseif (is_callable($playStationClientFactory)) {
            $this->legacyClientFactory = \Closure::fromCallable($playStationClientFactory);
        } else {
            $this->legacyClientFactory = $this->createDefaultLegacyClientFactory();
        }
        $this->npServiceNamePolicy = new NpServiceNamePolicy();
    }

    public static function fromDatabase(
        PDO $database,
        PlayStationClientFactoryInterface|callable|null $playStationClientFactory = null,
        ?PlayStationClientMode $clientMode = null
    ): self {
        $workerService = new WorkerService($database);
        $resolvedMode = $clientMode ?? PlayStationClientModeConfig::fromEnvironment($_ENV ?? [])->getMode();

        return new self(
            $database,
            static fn (): array => $workerService->fetchWorkers(),
            $playStationClientFactory,
            $resolvedMode
        );
    }

    /**
     * @return \Closure(): object
     */
    private function createDefaultLegacyClientFactory(): \Closure
    {
        return static function (): object {
            if (!class_exists(\Tustin\PlayStation\Client::class)) {
                throw new RuntimeException('Legacy PlayStation client is unavailable. Ensure composer autoload is loaded.');
            }

            return new \Tustin\PlayStation\Client();
        };
    }

    private function normalizePlayStationClientFactory(
        PlayStationClientFactoryInterface|callable|null $playStationClientFactory
    ): PlayStationClientFactoryInterface {
        if ($playStationClientFactory instanceof PlayStationClientFactoryInterface) {
            return $playStationClientFactory;
        }

        if (is_callable($playStationClientFactory)) {
            $legacyClientFactory = \Closure::fromCallable($playStationClientFactory);

            return new class ($legacyClientFactory) implements PlayStationClientFactoryInterface {
                /**
                 * @var \Closure(): PlayStationApiClientInterface
                 */
                private readonly \Closure $legacyClientFactory;

                /**
                 * @param \Closure(): PlayStationApiClientInterface $legacyClientFactory
                 */
                public function __construct(\Closure $legacyClientFactory)
                {
                    $this->legacyClientFactory = $legacyClientFactory;
                }

                public function createClient(): PlayStationApiClientInterface
                {
                    $client = ($this->legacyClientFactory)();

                    if ($client instanceof PlayStationApiClientInterface) {
                        return $client;
                    }

                    if (!is_object($client)) {
                        throw new RuntimeException('Invalid PlayStation client.');
                    }

                    return new class ($client) implements PlayStationApiClientInterface {
                        public function __construct(private readonly object $client)
                        {
                        }

                        public function loginWithNpsso(string $npsso): void
                        {
                            if (!method_exists($this->client, 'loginWithNpsso')) {
                                throw new RuntimeException('The PlayStation client does not support NPSSO authentication.');
                            }

                            $this->client->loginWithNpsso($npsso);
                        }

                        public function acquireAccessToken(): ?string
                        {
                            return null;
                        }

                        public function refreshAccessToken(): void
                        {
                            throw new RuntimeException('The PlayStation client does not support token refresh.');
                        }

                        public function lookupProfileByOnlineId(string $onlineId): mixed
                        {
                            throw new RuntimeException('The PlayStation client does not support profile requests.');
                        }

                        public function findUserByAccountId(string $accountId): object
                        {
                            if (!method_exists($this->client, 'users')) {
                                throw new RuntimeException('The PlayStation client does not support profile requests.');
                            }

                            return $this->client->users()->find($accountId);
                        }

                        public function requestTrophyEndpoint(string $path, array $query = [], array $headers = []): mixed
                        {
                            if (!method_exists($this->client, 'get')) {
                                throw new RuntimeException('The PlayStation client does not support trophy requests.');
                            }

                            return $this->client->get($path, $query, $headers);
                        }

                        public function searchUsers(string $onlineId): iterable
                        {
                            if (!method_exists($this->client, 'users')) {
                                return [];
                            }

                            return $this->client->users()->search($onlineId);
                        }
                    };
                }
            };
        }

        return new PlayStationClientFactory();
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
    public function fetchTrophyDataForNpCommunicationId(
        string $npCommunicationId,
        ?object $authenticatedClient = null
    ): array {
        $normalizedNpCommunicationId = trim($npCommunicationId);
        if ($normalizedNpCommunicationId === '') {
            throw new InvalidArgumentException('NP communication ID must be provided.');
        }

        return match ($this->clientMode) {
            PlayStationClientMode::Legacy => $this->fetchTrophyDataForNpCommunicationIdViaLegacyClient(
                $normalizedNpCommunicationId,
                $authenticatedClient
            ),
            PlayStationClientMode::New => $this->fetchTrophyDataForNpCommunicationIdViaNewClient(
                $normalizedNpCommunicationId,
                $authenticatedClient
            ),
            PlayStationClientMode::Shadow => $this->fetchTrophyDataForNpCommunicationIdInShadowMode(
                $normalizedNpCommunicationId,
                $authenticatedClient
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchTrophyDataForNpCommunicationIdViaLegacyClient(
        string $npCommunicationId,
        ?object $authenticatedClient = null
    ): array {
        $client = $authenticatedClient === null
            ? $this->createAuthenticatedLegacyClient()
            : $this->normalizeTrophyClient($authenticatedClient);

        return $this->fetchTrophyDataForNpCommunicationIdFromClient($npCommunicationId, $client);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchTrophyDataForNpCommunicationIdViaNewClient(
        string $npCommunicationId,
        ?object $authenticatedClient = null
    ): array {
        $client = $this->resolveNewModeTrophyClient($authenticatedClient);

        return $this->fetchTrophyDataForNpCommunicationIdFromClient($npCommunicationId, $client);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchTrophyDataForNpCommunicationIdInShadowMode(
        string $npCommunicationId,
        ?object $authenticatedClient = null
    ): array {
        $legacyResult = $this->fetchTrophyDataForNpCommunicationIdViaLegacyClient($npCommunicationId, null);

        try {
            $newResult = $this->fetchTrophyDataForNpCommunicationIdViaNewClient($npCommunicationId, null);
            $this->logShadowMismatchIfNeeded($npCommunicationId, $legacyResult, $newResult);
        } catch (Throwable $exception) {
            $this->logShadowExecutionFailure($npCommunicationId, $exception);
        }

        return $legacyResult;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchTrophyDataForNpCommunicationIdFromClient(
        string $normalizedNpCommunicationId,
        TrophyClientInterface $client
    ): array {
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
                if (!$this->shouldRetryWithDifferentServiceName($trophyGroupsException)) {
                    throw $trophyGroupsException;
                }

                $fallbackQuery = $this->npServiceNamePolicy->resolveAlternateQueryVariant(
                    $this->npServiceNamePolicy->buildLookupQueryVariants($preferredNpServiceName),
                    $trophyRequestResult['query']
                );
                $this->logLookupVariantSelection(
                    'trophyGroups',
                    $fallbackQuery ?? $trophyRequestResult['query'],
                    $this->describeFallbackTriggerReason($trophyGroupsException),
                    $fallbackQuery !== null
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

        return $this->npServiceNamePolicy->resolvePreferredNpServiceName((string) $platform);
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

    private function createAuthenticatedNewClient(): PlayStationApiClientInterface
    {
        foreach (($this->workerFetcher)() as $worker) {
            if (!$worker instanceof Worker) {
                continue;
            }

            $npsso = $worker->getNpsso();

            if ($npsso === '') {
                continue;
            }

            try {
                $client = $this->newClientFactory->createClient();
                $client->loginWithNpsso($npsso);

                return $client;
            } catch (Throwable) {
                continue;
            }
        }

        throw new RuntimeException('Unable to login to any worker accounts.');
    }

    private function createAuthenticatedLegacyClient(): TrophyClientInterface
    {
        foreach (($this->workerFetcher)() as $worker) {
            if (!$worker instanceof Worker) {
                continue;
            }

            $npsso = $worker->getNpsso();
            if ($npsso === '') {
                continue;
            }

            try {
                $client = ($this->legacyClientFactory)();
                if (!is_object($client) || !method_exists($client, 'loginWithNpsso') || !method_exists($client, 'get')) {
                    throw new RuntimeException('Invalid legacy PlayStation client.');
                }

                $client->loginWithNpsso($npsso);

                return $this->normalizeTrophyClient($client);
            } catch (Throwable) {
                continue;
            }
        }

        throw new RuntimeException('Unable to login to any worker accounts.');
    }

    private function normalizeTrophyClient(object $client): TrophyClientInterface
    {
        if ($client instanceof TrophyClientInterface) {
            return $client;
        }

        return new class ($client) implements TrophyClientInterface {
            public function __construct(private readonly object $client)
            {
            }

            public function requestTrophyEndpoint(string $path, array $query = [], array $headers = []): mixed
            {
                if (!method_exists($this->client, 'get')) {
                    throw new RuntimeException('The PlayStation client does not support trophy requests.');
                }

                return $this->client->get($path, $query, $headers);
            }
        };
    }

    private function resolveNewModeTrophyClient(?object $authenticatedClient): TrophyClientInterface
    {
        if ($authenticatedClient !== null) {
            return $this->normalizeTrophyClient($authenticatedClient);
        }

        return $this->createAuthenticatedNewClient();
    }

    /**
     * @param array{npServiceName?: string}|null $pinnedQuery
     * @return array{payload: mixed, query: array{npServiceName?: string}}
     */
    private function executeLookupRequest(
        TrophyClientInterface $client,
        string $path,
        ?string $preferredNpServiceName = null,
        ?array $pinnedQuery = null
    ): array
    {
        $queryVariants = $pinnedQuery === null
            ? $this->npServiceNamePolicy->buildLookupQueryVariants($preferredNpServiceName)
            : [$pinnedQuery];

        $lastException = null;

        foreach ($queryVariants as $query) {
            try {
                $this->logLookupVariantSelection(
                    $path,
                    $query,
                    null,
                    false
                );
                return [
                    'payload' => $client->requestTrophyEndpoint($path, $query, ['content-type' => 'application/json']),
                    'query' => $query,
                ];
            } catch (Throwable $exception) {
                $lastException = $exception;
                $this->logLookupVariantSelection(
                    $path,
                    $query,
                    $this->describeFallbackTriggerReason($exception),
                    true
                );

                if ($pinnedQuery !== null || !$this->shouldRetryWithDifferentServiceName($exception)) {
                    throw $exception;
                }
            }
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        throw new RuntimeException('Unable to retrieve trophy data from PlayStation Network.');
    }

    private function describeFallbackTriggerReason(Throwable $exception): string
    {
        $statusCode = $this->determineStatusCode($exception);
        if ($statusCode !== null) {
            return sprintf('http_status:%d', $statusCode);
        }

        return sprintf('exception:%s', $exception::class);
    }

    /**
     * @param array{npServiceName?: string} $queryVariant
     */
    private function logLookupVariantSelection(
        string $endpoint,
        array $queryVariant,
        ?string $fallbackTriggerReason,
        bool $isFallbackCandidate
    ): void {
        $payload = [
            'event' => 'psn_lookup_variant_selected',
            'endpoint' => $endpoint,
            'queryVariant' => $queryVariant,
            'isFallbackCandidate' => $isFallbackCandidate,
            'fallbackTriggerReason' => $fallbackTriggerReason,
        ];

        try {
            error_log((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            error_log('{"event":"psn_lookup_variant_selected","error":"failed_to_encode_log_payload"}');
        }
    }

    /**
     * @param array<string, mixed> $legacyResult
     * @param array<string, mixed> $newResult
     */
    private function logShadowMismatchIfNeeded(string $npCommunicationId, array $legacyResult, array $newResult): void
    {
        $normalizedLegacy = $this->normalizeForShadowComparison($legacyResult);
        $normalizedNew = $this->normalizeForShadowComparison($newResult);

        if ($normalizedLegacy === $normalizedNew) {
            return;
        }

        $this->logShadowEvent('psn_game_lookup_shadow_mismatch', [
            'mode' => $this->clientMode->value,
            'npCommunicationId' => $npCommunicationId,
            'legacy' => $normalizedLegacy,
            'new' => $normalizedNew,
        ]);
    }

    private function logShadowExecutionFailure(string $npCommunicationId, Throwable $exception): void
    {
        $this->logShadowEvent('psn_game_lookup_shadow_execution_failure', [
            'mode' => $this->clientMode->value,
            'npCommunicationId' => $npCommunicationId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function normalizeForShadowComparison(array $result): array
    {
        $firstTrophy = null;
        $groups = $result['trophyGroups'] ?? null;
        if (is_array($groups) && isset($groups[0]) && is_array($groups[0])) {
            $groupTrophies = $groups[0]['trophies'] ?? null;
            if (is_array($groupTrophies) && isset($groupTrophies[0]) && is_array($groupTrophies[0])) {
                $firstTrophy = $groupTrophies[0];
            }
        }

        return [
            'npCommunicationId' => (string) ($result['npCommunicationId'] ?? ''),
            'titleName' => (string) ($result['trophyTitleName'] ?? ''),
            'trophyGroupCount' => is_array($groups) ? count($groups) : 0,
            'firstTrophyId' => is_array($firstTrophy) ? (string) ($firstTrophy['trophyId'] ?? '') : '',
            'firstTrophyName' => is_array($firstTrophy) ? (string) ($firstTrophy['trophyName'] ?? '') : '',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function logShadowEvent(string $event, array $payload): void
    {
        $payload = ['event' => $event] + $payload;

        try {
            error_log((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            error_log(sprintf('{"event":"%s","error":"failed_to_encode_log_payload"}', $event));
        }
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
        if ($exception instanceof PlayStationTransientUpstreamException
            || $exception instanceof PlayStationAccessDeniedException
            || $exception instanceof PlayStationNotFoundException
            || $this->isThrowableClassNamed(
                $exception,
                [
                    'AccessDeniedHttpException',
                    'AccessDeniedException',
                    'NotFoundHttpException',
                    'NotFoundException',
                    'ApiException',
                ]
            )) {
            return true;
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof Throwable) {
            return $this->isRetryableKnownHttpException($previous);
        }

        return false;
    }

    /**
     * @param list<string> $classNames
     */
    private function isThrowableClassNamed(Throwable $exception, array $classNames): bool
    {
        $currentClass = $exception::class;

        foreach ($classNames as $className) {
            if ($currentClass === $className
                || str_ends_with($currentClass, '\\' . $className)
                || str_ends_with($currentClass, $className)) {
                return true;
            }
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof Throwable) {
            return $this->isThrowableClassNamed($previous, $classNames);
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
