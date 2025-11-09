<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/PsnPlayerSearchResult.php';
require_once __DIR__ . '/PsnPlayerSearchRateLimitException.php';
require_once __DIR__ . '/../PsnApi/autoload.php';

use PsnApi\PlayStationClient;
use PsnApi\HttpException;

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
            return new PlayStationClient();
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

        try {
            foreach ($client->users()->search($normalizedPlayerName) as $userSearchResult) {
                $results[] = PsnPlayerSearchResult::fromUserSearchResult($userSearchResult);
                $count++;

                if ($count >= self::RESULT_LIMIT) {
                    break;
                }
            }
        } catch (Throwable $exception) {
            $rateLimitException = $this->createRateLimitException($exception);

            if ($rateLimitException !== null) {
                throw $rateLimitException;
            }

            throw $exception;
        }

        return $results;
    }

    private function createRateLimitException(Throwable $exception): ?PsnPlayerSearchRateLimitException
    {
        $retryAt = $this->extractRateLimitRetryAt($exception);

        if ($retryAt === null) {
            return null;
        }

        return new PsnPlayerSearchRateLimitException($retryAt, previous: $exception);
    }

    private function extractRateLimitRetryAt(Throwable $exception): ?DateTimeImmutable
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof HttpException && $current->getStatusCode() === 429) {
                $retryAt = $this->parseRetryAt($current->getHeaderLine('X-RateLimit-Next-Available'));

                if ($retryAt !== null) {
                    return $retryAt;
                }
            }

            $response = $this->extractResponseFromException($current);

            if ($response !== null && (int) $response->getStatusCode() === 429) {
                $retryAt = $this->parseRetryAt((string) $response->getHeaderLine('X-RateLimit-Next-Available'));

                if ($retryAt !== null) {
                    return $retryAt;
                }
            }
        }

        return null;
    }

    private function parseRetryAt(string $headerValue): ?DateTimeImmutable
    {
        $trimmedValue = trim($headerValue);

        if ($trimmedValue === '') {
            return null;
        }

        if (ctype_digit($trimmedValue)) {
            $timestamp = (int) $trimmedValue;

            return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'));
        }

        $timestamp = strtotime($trimmedValue);

        if ($timestamp !== false) {
            return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'));
        }

        return null;
    }

    private function extractResponseFromException(Throwable $exception): ?object
    {
        if (!method_exists($exception, 'getResponse')) {
            return null;
        }

        $response = $exception->getResponse();

        if (!is_object($response)) {
            return null;
        }

        if (!method_exists($response, 'getStatusCode') || !method_exists($response, 'getHeaderLine')) {
            return null;
        }

        return $response;
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
            } catch (Throwable $exception) {
                continue;
            }
        }

        throw new RuntimeException('Unable to login to any worker accounts.');
    }
}
