<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/PsnPlayerSearchResult.php';
require_once __DIR__ . '/PsnPlayerSearchRateLimitException.php';

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
        $response = $this->findResponse($exception);

        if ($response !== null) {
            $statusCode = $this->extractStatusCodeFromResponse($response);

            if ($statusCode === 429) {
                $retryAt = $this->extractRetryTimestampFromResponse($response);

                return new PsnPlayerSearchRateLimitException($retryAt, $exception);
            }
        }

        $throwableStatusCode = $this->extractStatusCodeFromThrowable($exception);

        if ($throwableStatusCode === 429) {
            return new PsnPlayerSearchRateLimitException(null, $exception);
        }

        return null;
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

    private function extractRetryTimestampFromResponse(object $response): ?DateTimeImmutable
    {
        $headerValue = null;

        if (method_exists($response, 'getHeaderLine')) {
            $headerLine = $response->getHeaderLine('X-RateLimit-Next-Available');

            if (is_string($headerLine)) {
                $headerLine = trim($headerLine);

                if ($headerLine !== '') {
                    $headerValue = $headerLine;
                }
            }
        }

        if ($headerValue === null && method_exists($response, 'getHeader')) {
            $header = $response->getHeader('X-RateLimit-Next-Available');

            if (is_array($header) && $header !== []) {
                $firstValue = $header[0];

                if (is_string($firstValue)) {
                    $firstValue = trim($firstValue);

                    if ($firstValue !== '') {
                        $headerValue = $firstValue;
                    }
                }
            }
        }

        return $this->parseRetryTimestamp($headerValue);
    }

    private function parseRetryTimestamp(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;

            if ($timestamp > 0) {
                try {
                    $dateTime = new DateTimeImmutable('@' . $timestamp);

                    return $dateTime->setTimezone(new DateTimeZone('UTC'));
                } catch (Exception) {
                    return null;
                }
            }
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
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
