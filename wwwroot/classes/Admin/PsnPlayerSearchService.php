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
        $attemptedWorkers = 0;

        try {
            $workers = ($this->workerFetcher)();
        } catch (Throwable $exception) {
            $message = 'Failed to fetch admin workers: ' . $this->describeException($exception);
            error_log('[AdminPlayerSearch] ' . $message);

            throw new RuntimeException($message, 0, $exception);
        }

        if (!is_iterable($workers)) {
            $message = sprintf(
                'Worker fetcher returned a non-iterable value of type %s.',
                get_debug_type($workers)
            );

            error_log('[AdminPlayerSearch] ' . $message);

            throw new RuntimeException($message);
        }

        foreach ($workers as $index => $worker) {
            $attemptedWorkers++;

            if (!$worker instanceof Worker) {
                $message = sprintf(
                    'Worker fetcher yielded a non-Worker value of type %s at index %s.',
                    get_debug_type($worker),
                    is_scalar($index) || $index === null ? (string) $index : gettype($index)
                );

                $failureMessages[] = $message;
                error_log('[AdminPlayerSearch] ' . $message);

                continue;
            }

            $workerFound = true;
            $npsso = trim($worker->getNpsso());
            $refreshToken = trim($worker->getRefreshToken());

            if ($npsso === '' && $refreshToken === '') {
                $message = sprintf(
                    'Worker %d is missing both NPSSO and refresh token credentials.',
                    $worker->getId()
                );

                $failureMessages[] = $message;
                error_log('[AdminPlayerSearch] ' . $message);

                continue;
            }

            try {
                $client = $factory();

                if (!is_object($client)) {
                    throw new RuntimeException('Invalid PlayStation client.');
                }
            } catch (Throwable $exception) {
                $lastError = $exception;
                $message = sprintf(
                    'Worker %d failed to create a PlayStation client: %s',
                    $worker->getId(),
                    $this->describeException($exception)
                );
                $failureMessages[] = $message;
                error_log('[AdminPlayerSearch] ' . $message);

                continue;
            }

            try {
                $this->authenticateClient($client, $worker);

                return $client;
            } catch (Throwable $exception) {
                $lastError = $exception;
                $message = sprintf(
                    'Worker %d authentication failed: %s',
                    $worker->getId(),
                    $this->describeException($exception)
                );
                $failureMessages[] = $message;
                error_log('[AdminPlayerSearch] ' . $message);
            }
        }

        $message = 'Unable to login to any worker accounts.';

        if ($workerFound) {
            $message .= sprintf(' Checked %d worker(s).', max(1, $attemptedWorkers));
        }

        if ($failureMessages !== []) {
            $message .= ' Failures: ' . implode(' | ', $failureMessages);
        } elseif (!$workerFound) {
            $message .= ' No workers were returned by the worker service.';
        }

        if ($lastError instanceof Throwable) {
            error_log('[AdminPlayerSearch] ' . $message);
            throw new RuntimeException($message, 0, $lastError);
        }

        error_log('[AdminPlayerSearch] ' . $message);
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
                $attemptErrors[] = sprintf(
                    '%s login failed: %s',
                    $attempt['label'],
                    $this->describeException($exception)
                );
            }
        }

        if ($lastError instanceof Throwable) {
            $message = 'All authentication attempts failed.';

            if ($attemptErrors !== []) {
                $message .= ' ' . implode(' ', $attemptErrors);
            }

            error_log('[AdminPlayerSearch] ' . $message);

            throw new RuntimeException($message, 0, $lastError);
        }

        throw new RuntimeException('Failed to authenticate with the provided credentials.');
    }

    private function describeException(Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        $description = get_class($exception);

        if ($message !== '') {
            $description .= ': ' . $message;
        }

        $code = $exception->getCode();

        if (is_int($code) && $code !== 0) {
            $description .= sprintf(' (code %d)', $code);
        }

        return $description;
    }
}
