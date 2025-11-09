<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/PsnPlayerSearchResult.php';

use Tustin\PlayStation\Client;

final class PsnPlayerSearchService
{
    private const RESULT_LIMIT = 50;

    /**
     * @var callable(): iterable<Worker>
     */
    private $workerFetcher;

    /**
     * @var callable(): object
     */
    private $clientFactory;

    /**
     * @param callable(): iterable<Worker> $workerFetcher
     * @param callable(): object|null $clientFactory
     */
    public function __construct(callable $workerFetcher, ?callable $clientFactory = null)
    {
        $this->workerFetcher = $workerFetcher;
        $this->clientFactory = $clientFactory ?? static function (): object {
            return new Client();
        };
    }

    public static function fromDatabase(PDO $database): self
    {
        $workerService = new WorkerService($database);

        return new self(static fn (): array => $workerService->fetchWorkers());
    }

    public static function getResultLimit(): int
    {
        return self::RESULT_LIMIT;
    }

    /**
     * @return list<PsnPlayerSearchResult>
     */
    public function search(string $playerName): array
    {
        $normalizedPlayerName = trim($playerName);

        if ($normalizedPlayerName === '') {
            return [];
        }

        $client = $this->createAuthenticatedClient();

        $results = [];
        $count = 0;

        foreach ($client->users()->search($normalizedPlayerName) as $userSearchResult) {
            $results[] = PsnPlayerSearchResult::fromUserSearchResult($userSearchResult);
            $count++;

            if ($count >= self::RESULT_LIMIT) {
                break;
            }
        }

        return $results;
    }

    private function createAuthenticatedClient(): object
    {
        $factory = $this->clientFactory;
        $lastError = null;

        foreach (($this->workerFetcher)() as $worker) {
            if (!$worker instanceof Worker) {
                continue;
            }

            $npsso = trim($worker->getNpsso());
            $refreshToken = trim($worker->getRefreshToken());

            if ($npsso === '' && $refreshToken === '') {
                continue;
            }

            try {
                $client = $factory();

                if (!is_object($client)) {
                    throw new RuntimeException('Invalid PlayStation client.');
                }

                $this->authenticateClient($client, $npsso, $refreshToken);

                return $client;
            } catch (Throwable $exception) {
                $lastError = $exception;
            }
        }

        if ($lastError instanceof Throwable) {
            throw new RuntimeException('Unable to login to any worker accounts.', 0, $lastError);
        }

        throw new RuntimeException('Unable to login to any worker accounts.');
    }

    private function authenticateClient(object $client, string $npsso, string $refreshToken): void
    {
        $supportsNpsso = method_exists($client, 'loginWithNpsso');
        $supportsRefreshToken = method_exists($client, 'loginWithRefreshToken');

        if (!$supportsNpsso && !$supportsRefreshToken) {
            throw new RuntimeException('The PlayStation client does not support authentication.');
        }

        $attempts = [];

        if ($supportsNpsso && $npsso !== '') {
            $attempts[] = static function () use ($client, $npsso): void {
                $client->loginWithNpsso($npsso);
            };
        }

        if ($supportsRefreshToken && $refreshToken !== '') {
            $attempts[] = static function () use ($client, $refreshToken): void {
                $client->loginWithRefreshToken($refreshToken);
            };
        }

        if ($attempts === []) {
            throw new RuntimeException('Worker is missing authentication credentials.');
        }

        $lastError = null;

        foreach ($attempts as $attempt) {
            try {
                $attempt();

                return;
            } catch (Throwable $exception) {
                $lastError = $exception;
            }
        }

        if ($lastError instanceof Throwable) {
            throw $lastError;
        }

        throw new RuntimeException('Failed to authenticate with the provided credentials.');
    }
}
