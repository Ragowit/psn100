<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/PsnPlayerLookupException.php';
require_once __DIR__ . '/../PlayStationClientMode.php';
require_once __DIR__ . '/../PlayStationClientModeConfig.php';
require_once __DIR__ . '/../PlayStation/Contracts/PlayStationClientFactoryInterface.php';
require_once __DIR__ . '/../PlayStation/Contracts/PlayStationApiClientInterface.php';
require_once __DIR__ . '/../PlayStation/Contracts/ProfileClientInterface.php';
require_once __DIR__ . '/../PlayStation/PlayStationClientFactory.php';

final class PsnPlayerLookupService
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

    /**
     * @param callable(): iterable<Worker> $workerFetcher
     */
    public function __construct(
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
        } elseif ($playStationClientFactory instanceof PlayStationClientFactoryInterface) {
            $this->legacyClientFactory = static fn (): PlayStationApiClientInterface => $playStationClientFactory->createClient();
        } elseif (is_callable($playStationClientFactory)) {
            $this->legacyClientFactory = \Closure::fromCallable($playStationClientFactory);
        } else {
            $this->legacyClientFactory = $this->createDefaultLegacyClientFactory();
        }
    }

    public static function fromDatabase(
        PDO $database,
        PlayStationClientFactoryInterface|callable|null $playStationClientFactory = null,
        ?PlayStationClientMode $clientMode = null
    ): self {
        $workerService = new WorkerService($database);
        $resolvedMode = $clientMode ?? PlayStationClientModeConfig::fromEnvironment($_ENV ?? [])->getMode();

        return new self(
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
        return static fn (): object => new \Tustin\PlayStation\Client();
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
                            if (!method_exists($this->client, 'get')) {
                                throw new RuntimeException('The PlayStation client does not support profile requests.');
                            }

                            $path = sprintf(
                                'https://us-prof.np.community.playstation.net/userProfile/v1/users/%s/profile2',
                                rawurlencode($onlineId)
                            );

                            return $this->client->get(
                                $path,
                                ['fields' => 'accountId,onlineId,currentOnlineId,npId'],
                                ['content-type' => 'application/json']
                            );
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
    public function lookup(string $onlineId): array
    {
        $normalizedOnlineId = trim($onlineId);

        if ($normalizedOnlineId === '') {
            throw new InvalidArgumentException('Online ID cannot be blank.');
        }

        return match ($this->clientMode) {
            PlayStationClientMode::Legacy => $this->executeLookupWithErrorHandling(
                fn (): array => $this->lookupViaLegacyClient($normalizedOnlineId),
                $normalizedOnlineId
            ),
            PlayStationClientMode::New => $this->executeLookupWithErrorHandling(
                fn (): array => $this->lookupViaNewClient($normalizedOnlineId),
                $normalizedOnlineId
            ),
            PlayStationClientMode::Shadow => $this->executeShadowLookup($normalizedOnlineId),
        };
    }

    /**
     * @param callable(): array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function executeLookupWithErrorHandling(callable $operation, string $normalizedOnlineId): array
    {
        try {
            return $operation();
        } catch (Throwable $exception) {
            $statusCode = $this->determineStatusCode($exception);

            if ($this->isAuthenticationBootstrapFailure($exception, $statusCode)) {
                throw $exception;
            }

            if ($statusCode === 404) {
                throw new PsnPlayerLookupException(
                    sprintf('Player "%s" was not found.', $normalizedOnlineId),
                    $statusCode,
                    $exception
                );
            }

            throw new PsnPlayerLookupException(
                'Failed to retrieve the player profile from PlayStation Network. Please try again later.',
                $statusCode,
                $exception
            );
        }
    }

    private function isAuthenticationBootstrapFailure(Throwable $exception, ?int $statusCode): bool
    {
        if (!$exception instanceof RuntimeException || $statusCode !== null) {
            return false;
        }

        return $exception->getMessage() === 'Unable to login to any worker accounts.';
    }

    /**
     * @return array<string, mixed>
     */
    private function executeShadowLookup(string $normalizedOnlineId): array
    {
        $legacyResult = $this->executeLookupWithErrorHandling(
            fn (): array => $this->lookupViaLegacyClient($normalizedOnlineId),
            $normalizedOnlineId
        );

        try {
            $newResult = $this->executeLookupWithErrorHandling(
                fn (): array => $this->lookupViaNewClient($normalizedOnlineId),
                $normalizedOnlineId
            );
            $this->logShadowMismatchIfNeeded($legacyResult, $newResult, $normalizedOnlineId);
        } catch (Throwable $exception) {
            $this->logShadowExecutionFailure($normalizedOnlineId, $exception);
        }

        return $legacyResult;
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupViaLegacyClient(string $onlineId): array
    {
        $client = $this->createAuthenticatedLegacyClient();
        $profile = $this->executeUserProfileRequest($client, $onlineId);

        return $this->normalizeProfileResponse($profile);
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupViaNewClient(string $onlineId): array
    {
        $client = $this->createAuthenticatedNewClient();
        $profile = $this->executeUserProfileRequest($client, $onlineId);

        return $this->normalizeProfileResponse($profile);
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

    private function createAuthenticatedLegacyClient(): ProfileClientInterface
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
                if ($client instanceof PlayStationApiClientInterface) {
                    $client->loginWithNpsso($npsso);

                    return $client;
                }

                if (!is_object($client) || !method_exists($client, 'loginWithNpsso')) {
                    throw new RuntimeException('Invalid legacy PlayStation client.');
                }

                $client->loginWithNpsso($npsso);

                return $this->normalizeLegacyProfileClient($client);
            } catch (Throwable) {
                continue;
            }
        }

        throw new RuntimeException('Unable to login to any worker accounts.');
    }

    private function normalizeLegacyProfileClient(object $client): ProfileClientInterface
    {
        if ($client instanceof ProfileClientInterface) {
            return $client;
        }

        return new class ($client) implements ProfileClientInterface {
            public function __construct(private readonly object $client)
            {
            }

            public function lookupProfileByOnlineId(string $onlineId): mixed
            {
                if (!method_exists($this->client, 'get')) {
                    throw new RuntimeException('The PlayStation client does not support profile requests.');
                }

                $path = sprintf(
                    'https://us-prof.np.community.playstation.net/userProfile/v1/users/%s/profile2',
                    rawurlencode($onlineId)
                );

                return $this->client->get(
                    $path,
                    ['fields' => 'accountId,onlineId,currentOnlineId,npId'],
                    ['content-type' => 'application/json']
                );
            }

            public function findUserByAccountId(string $accountId): object
            {
                if (!method_exists($this->client, 'users')) {
                    throw new RuntimeException('The PlayStation client does not support profile requests.');
                }

                return $this->client->users()->find($accountId);
            }
        };
    }

    private function executeUserProfileRequest(ProfileClientInterface $client, string $onlineId): mixed
    {
        return $client->lookupProfileByOnlineId($onlineId);
    }

    /**
     * @param array<string, mixed> $legacyResult
     * @param array<string, mixed> $newResult
     */
    private function logShadowMismatchIfNeeded(array $legacyResult, array $newResult, string $fallbackOnlineId): void
    {
        $normalizedLegacy = $this->normalizeForShadowComparison($legacyResult);
        $normalizedNew = $this->normalizeForShadowComparison($newResult);

        if ($normalizedLegacy === $normalizedNew) {
            return;
        }

        $legacyProfile = is_array($legacyResult['profile'] ?? null) ? $legacyResult['profile'] : [];
        $newProfile = is_array($newResult['profile'] ?? null) ? $newResult['profile'] : [];

        $context = [
            'mode' => $this->clientMode->value,
            'onlineId' => (string) ($legacyProfile['onlineId'] ?? $newProfile['onlineId'] ?? $fallbackOnlineId),
            'accountId' => (string) ($legacyProfile['accountId'] ?? $newProfile['accountId'] ?? ''),
            'legacy' => $normalizedLegacy,
            'new' => $normalizedNew,
        ];

        $this->logShadowEvent('psn_player_lookup_shadow_mismatch', $context);
    }

    private function logShadowExecutionFailure(string $onlineId, Throwable $exception): void
    {
        $this->logShadowEvent('psn_player_lookup_shadow_execution_failure', [
            'mode' => $this->clientMode->value,
            'onlineId' => $onlineId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function normalizeForShadowComparison(array $result): array
    {
        $profile = is_array($result['profile'] ?? null) ? $result['profile'] : [];

        return [
            'accountId' => (string) ($profile['accountId'] ?? ''),
            'onlineId' => (string) ($profile['onlineId'] ?? ''),
            'currentOnlineId' => (string) ($profile['currentOnlineId'] ?? ''),
            'npId' => (string) ($profile['npId'] ?? ''),
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

    private function determineStatusCode(Throwable $exception): ?int
    {
        $response = $this->findResponse($exception);

        if ($response !== null) {
            $status = $this->extractStatusCodeFromResponse($response);

            if ($status !== null) {
                return $status;
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
    private function normalizeProfileResponse(mixed $profile): array
    {
        if (is_array($profile)) {
            return $profile;
        }

        if (is_object($profile)) {
            try {
                $encoded = json_encode($profile, JSON_THROW_ON_ERROR);
                $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (JsonException) {
            }

            return get_object_vars($profile);
        }

        return [];
    }
}
