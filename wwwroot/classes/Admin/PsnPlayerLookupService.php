<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/PsnPlayerLookupException.php';
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

    private readonly PlayStationClientFactoryInterface $playStationClientFactory;

    /**
     * @param callable(): iterable<Worker> $workerFetcher
     */
    public function __construct(
        callable $workerFetcher,
        PlayStationClientFactoryInterface|callable|null $playStationClientFactory = null
    ) {
        $this->workerFetcher = \Closure::fromCallable($workerFetcher);
        $this->playStationClientFactory = $this->normalizePlayStationClientFactory($playStationClientFactory);
    }

    public static function fromDatabase(
        PDO $database,
        PlayStationClientFactoryInterface|callable|null $playStationClientFactory = null
    ): self {
        $workerService = new WorkerService($database);

        return new self(
            static fn (): array => $workerService->fetchWorkers(),
            $playStationClientFactory
        );
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

        $client = $this->createAuthenticatedClient();

        try {
            $profile = $this->executeUserProfileRequest($client, $normalizedOnlineId);
        } catch (Throwable $exception) {
            $statusCode = $this->determineStatusCode($exception);

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

        return $this->normalizeProfileResponse($profile);
    }

    private function createAuthenticatedClient(): PlayStationApiClientInterface
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
                $client = $this->playStationClientFactory->createClient();
                $client->loginWithNpsso($npsso);

                return $client;
            } catch (Throwable) {
                continue;
            }
        }

        throw new RuntimeException('Unable to login to any worker accounts.');
    }

    private function executeUserProfileRequest(ProfileClientInterface $client, string $onlineId): mixed
    {
        return $client->lookupProfileByOnlineId($onlineId);
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
