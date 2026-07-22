<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/PsnPlayerLookupException.php';
require_once __DIR__ . '/../PsnHttpExceptionClassifier.php';

use Tustin\PlayStation\Client;

final class PsnPlayerLookupService
{
    private const \Closure DEFAULT_CLIENT_FACTORY = static function (): object {
        return new Client();
    };

    private const \Closure DEFAULT_REFRESH_TOKEN_SAVER = static function (
        int $workerId,
        #[\SensitiveParameter] string $refreshToken,
    ): void {
    };

    /**
     * @var \Closure(): iterable<Worker>
     */
    private readonly \Closure $workerFetcher;

    /**
     * @var \Closure(): object
     */
    private readonly \Closure $clientFactory;
    /**
     * @var \Closure(int, string): void
     */
    private readonly \Closure $refreshTokenSaver;

    /**
     * @param callable(): iterable<Worker> $workerFetcher
     * @param callable(): object|null $clientFactory
     */
    public function __construct(callable $workerFetcher, ?callable $clientFactory = null, ?callable $refreshTokenSaver = null)
    {
        $this->workerFetcher = $workerFetcher(...);
        $this->clientFactory = ($clientFactory ?? self::DEFAULT_CLIENT_FACTORY)(...);
        $this->refreshTokenSaver = ($refreshTokenSaver ?? self::DEFAULT_REFRESH_TOKEN_SAVER)(...);
    }

    #[\NoDiscard]
    public static function fromDatabase(PDO $database): self
    {
        $workerService = new WorkerService($database);

        return new self(
            static fn (): array => $workerService->fetchWorkers(),
            null,
            static fn (int $workerId, #[\SensitiveParameter] string $refreshToken): bool => $workerService->updateWorkerRefreshToken($workerId, $refreshToken)
        );
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
            $normalizedProfile = $this->normalizeProfileResponse($profile);
        } catch (Throwable $exception) {
            $statusCode = PsnHttpExceptionClassifier::determineStatusCode($exception);

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

        $trophySummary = null;

        try {
            $trophySummary = $this->fetchTrophySummary($client, $normalizedProfile);
        } catch (Throwable) {
            $trophySummary = null;
        }

        if ($trophySummary !== null) {
            $normalizedProfile['trophySummary'] = $trophySummary;
        }

        return $normalizedProfile;
    }

    private function createAuthenticatedClient(): object
    {
        $factory = $this->clientFactory;

        foreach (($this->workerFetcher)() as $worker) {
            if (!$worker instanceof Worker) {
                continue;
            }

            $refreshToken = $worker->getRefreshToken();
            $npsso = $worker->getNpsso();

            if ($refreshToken === '' && $npsso === '') {
                continue;
            }

            try {
                $client = $factory();

                if (!is_object($client)) {
                    throw new RuntimeException('Invalid PlayStation client.');
                }

                if ($refreshToken !== '' && method_exists($client, 'loginWithRefreshToken')) {
                    try {
                        $client->loginWithRefreshToken($refreshToken);
                        $this->persistRefreshTokenBestEffort($worker->getId(), $client);

                        return $client;
                    } catch (Throwable) {
                        // Fall back to NPSSO for this worker below.
                    }
                }

                if (!method_exists($client, 'loginWithNpsso')) {
                    throw new RuntimeException('The PlayStation client does not support NPSSO authentication.');
                }

                $client->loginWithNpsso($npsso);
                $this->persistRefreshTokenBestEffort($worker->getId(), $client);

                return $client;
            } catch (Throwable) {
                continue;
            }
        }

        throw new RuntimeException('Unable to login to any worker accounts.');
    }

    private function persistRefreshTokenBestEffort(int $workerId, object $client): void
    {
        try {
            $this->saveRefreshToken($workerId, $client);
        } catch (Throwable) {
            // Refresh-token persistence is best-effort and must not fail authentication.
        }
    }

    private function saveRefreshToken(int $workerId, object $client): void
    {
        if (!method_exists($client, 'getRefreshToken')) {
            return;
        }

        $refreshToken = $client->getRefreshToken();
        if (!is_object($refreshToken) || !method_exists($refreshToken, 'getToken')) {
            return;
        }

        $tokenValue = $refreshToken->getToken();
        if (!is_string($tokenValue) || $tokenValue === '') {
            return;
        }

        ($this->refreshTokenSaver)($workerId, $tokenValue);
    }

    private function executeUserProfileRequest(object $client, string $onlineId): mixed
    {
        if (!method_exists($client, 'get')) {
            throw new RuntimeException('The PlayStation client does not support profile requests.');
        }

        $path = sprintf(
            'https://us-prof.np.community.playstation.net/userProfile/v1/users/%s/profile2',
            rawurlencode($onlineId)
        );

        $query = [
            'fields' => 'accountId,onlineId,currentOnlineId,npId',
        ];

        return $client->get($path, $query, ['content-type' => 'application/json']);
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>|null
     */
    private function fetchTrophySummary(object $client, array $profile): ?array
    {
        if (!method_exists($client, 'get')) {
            return null;
        }

        $accountId = $profile['profile']['accountId'] ?? null;

        if (!is_string($accountId) || $accountId === '') {
            return null;
        }

        $path = sprintf(
            'https://m.np.playstation.com/api/trophy/v1/users/%s/trophySummary',
            rawurlencode($accountId)
        );

        $summary = $client->get($path, [], ['content-type' => 'application/json']);
        $normalizedSummary = $this->normalizeProfileResponse($summary);

        return $normalizedSummary === [] ? null : $normalizedSummary;
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
