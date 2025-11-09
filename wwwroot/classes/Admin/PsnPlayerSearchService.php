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
        $failureMessages = [];
        $workerFound = false;

        foreach (($this->workerFetcher)() as $worker) {
            if (!$worker instanceof Worker) {
                $failureMessages[] = 'Encountered a non-worker entry while fetching admin workers.';

                continue;
            }

            $workerFound = true;
            $npsso = trim($worker->getNpsso());
            $refreshToken = trim($worker->getRefreshToken());

            if ($npsso === '' && $refreshToken === '') {
                $failureMessages[] = sprintf('Worker %d is missing both NPSSO and refresh token credentials.', $worker->getId());

                continue;
            }

            try {
                $client = $factory();

                if (!is_object($client)) {
                    throw new RuntimeException('Invalid PlayStation client.');
                }
            } catch (Throwable $exception) {
                $lastError = $exception;
                $failureMessages[] = sprintf(
                    'Worker %d failed to create a PlayStation client: %s',
                    $worker->getId(),
                    $exception->getMessage()
                );

                continue;
            }

            try {
                $this->authenticateClient($client, $worker);

                return $client;
            } catch (Throwable $exception) {
                $lastError = $exception;
                $failureMessages[] = sprintf(
                    'Worker %d authentication failed: %s',
                    $worker->getId(),
                    $exception->getMessage()
                );
            }
        }

        $message = 'Unable to login to any worker accounts.';

        if ($failureMessages !== []) {
            $message .= ' Failures: ' . implode(' | ', $failureMessages);
        } elseif (!$workerFound) {
            $message .= ' No workers were returned by the worker service.';
        }

        if ($lastError instanceof Throwable) {
            throw new RuntimeException($message, 0, $lastError);
        }

        throw new RuntimeException($message);
    }

    private function authenticateClient(object $client, Worker $worker): void
    {
        $supportsNpsso = method_exists($client, 'loginWithNpsso');
        $supportsRefreshToken = method_exists($client, 'loginWithRefreshToken');

        if (!$supportsNpsso && !$supportsRefreshToken) {
            throw new RuntimeException('The PlayStation client does not support authentication.');
        }

        $attempts = [];
        $npsso = trim($worker->getNpsso());
        $refreshToken = trim($worker->getRefreshToken());

        if ($supportsNpsso && $npsso !== '') {
            $attempts[] = [
                'label' => 'NPSSO',
                'callback' => static function () use ($client, $npsso): void {
                    $client->loginWithNpsso($npsso);
                },
            ];
        }

        if ($supportsRefreshToken && $refreshToken !== '') {
            $attempts[] = [
                'label' => 'refresh-token',
                'callback' => static function () use ($client, $refreshToken): void {
                    $client->loginWithRefreshToken($refreshToken);
                },
            ];
        }

        if ($attempts === []) {
            throw new RuntimeException('Worker is missing usable authentication credentials.');
        }

        $lastError = null;
        $attemptErrors = [];

        foreach ($attempts as $attempt) {
            try {
                $attempt['callback']();

                return;
            } catch (Throwable $exception) {
                $lastError = $exception;
                $attemptErrors[] = sprintf('%s login failed: %s', $attempt['label'], $exception->getMessage());
            }
        }

        if ($lastError instanceof Throwable) {
            $message = 'All authentication attempts failed.';

            if ($attemptErrors !== []) {
                $message .= ' ' . implode(' ', $attemptErrors);
            }

            throw new RuntimeException($message, 0, $lastError);
        }

        throw new RuntimeException('Failed to authenticate with the provided credentials.');
    }
}
