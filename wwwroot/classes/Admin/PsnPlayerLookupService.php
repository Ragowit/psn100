<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/PsnPlayerLookupException.php';

use Tustin\PlayStation\Client;

final class PsnPlayerLookupService
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
     * @param callable(): iterable<Worker> $workerFetcher
     * @param callable(): object|null $clientFactory
     */
    public function __construct(callable $workerFetcher, ?callable $clientFactory = null)
    {
        $this->workerFetcher = \Closure::fromCallable($workerFetcher);
        $this->clientFactory = \Closure::fromCallable(
            $clientFactory ?? static function (): object {
                return new Client();
            }
        );
    }

    public static function fromDatabase(PDO $database): self
    {
        $workerService = new WorkerService($database);

        return new self(static fn (): array => $workerService->fetchWorkers());
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

                if (!is_object($client)) {
                    throw new RuntimeException('Invalid PlayStation client.');
                }

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
