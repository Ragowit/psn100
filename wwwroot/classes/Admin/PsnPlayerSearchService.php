<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/PsnPlayerSearchResult.php';
require_once __DIR__ . '/PsnPlayerSearchRateLimitException.php';

use Tustin\PlayStation\Client;

final class PsnPlayerSearchService
{
    private const RESULT_LIMIT = 50;

    private const GRAPHQL_ENDPOINT = 'graphql/v1/op';

    private const DEFAULT_OPERATION_NAME = 'searchUniversalSearch';

    private const DEFAULT_PERSISTED_QUERY_HASH = '';

    /**
     * @var callable(): iterable<Worker>
     */
    private $workerFetcher;

    /**
     * @var callable(): object
     */
    private $clientFactory;

    /**
     * @var callable(object, string): array<array{onlineId: string, accountId: string, country: string}>
     */
    private $graphQlExecutor;

    /**
     * @param callable(): iterable<Worker> $workerFetcher
     * @param callable(): object|null $clientFactory
     * @param callable(object, string): array<array{onlineId: string, accountId: string, country: string}>|null $graphQlExecutor
     */
    public function __construct(callable $workerFetcher, ?callable $clientFactory = null, ?callable $graphQlExecutor = null)
    {
        $this->workerFetcher = $workerFetcher;
        $this->clientFactory = $clientFactory ?? static function (): object {
            return new Client();
        };
        $this->graphQlExecutor = $graphQlExecutor ?? function (object $client, string $searchTerm): array {
            return $this->executeGraphQlSearch($client, $searchTerm);
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

        try {
            $results = $this->transformGraphQlResults((($this->graphQlExecutor)($client, $normalizedPlayerName)));
        } catch (Throwable $exception) {
            $rateLimitException = $this->createRateLimitException($exception);

            if ($rateLimitException !== null) {
                throw $rateLimitException;
            }

            throw $exception;
        }

        return $results;
    }

    /**
     * @param array<array{onlineId: string, accountId: string, country: string}> $rawResults
     * @return list<PsnPlayerSearchResult>
     */
    private function transformGraphQlResults(array $rawResults): array
    {
        $results = [];

        foreach ($rawResults as $index => $rawResult) {
            if ($index >= self::RESULT_LIMIT) {
                break;
            }

            $results[] = new PsnPlayerSearchResult(
                (string) ($rawResult['onlineId'] ?? ''),
                (string) ($rawResult['accountId'] ?? ''),
                (string) ($rawResult['country'] ?? '')
            );
        }

        return $results;
    }

    /**
     * @return array<array{onlineId: string, accountId: string, country: string}>
     */
    private function executeGraphQlSearch(object $client, string $searchTerm): array
    {
        $operationName = $this->resolveOperationName();
        $persistedQueryHash = $this->resolvePersistedQueryHash();

        $variables = $this->createGraphQlVariables($searchTerm);

        try {
            $queryParameters = [
                'operationName' => $operationName,
                'variables' => json_encode($variables, JSON_THROW_ON_ERROR),
                'extensions' => json_encode([
                    'persistedQuery' => [
                        'version' => 1,
                        'sha256Hash' => $persistedQueryHash,
                    ],
                ], JSON_THROW_ON_ERROR),
            ];
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode GraphQL request parameters.', 0, $exception);
        }

        $headers = [
            'X-Apollo-Operation-Name' => $operationName,
            'Accept' => 'application/json',
        ];

        $response = $client->get(self::GRAPHQL_ENDPOINT, $queryParameters, $headers);

        return $this->extractGraphQlResults($response, $operationName);
    }

    /**
     * @return array<array{onlineId: string, accountId: string, country: string}>
     */
    private function extractGraphQlResults(mixed $response, string $operationName): array
    {
        if (is_object($response)) {
            $response = json_decode(json_encode($response, JSON_THROW_ON_ERROR), true);
        }

        if (!is_array($response)) {
            return [];
        }

        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            return [];
        }

        $searchData = $data[$operationName] ?? null;

        if (!is_array($searchData)) {
            return [];
        }

        $domainResponses = $searchData['domainResponses'] ?? [];

        if (!is_array($domainResponses)) {
            return [];
        }

        $results = [];

        foreach ($domainResponses as $domainResponse) {
            if (is_object($domainResponse)) {
                $domainResponse = json_decode(json_encode($domainResponse, JSON_THROW_ON_ERROR), true);
            }

            if (!is_array($domainResponse)) {
                continue;
            }

            $entries = $domainResponse['results'] ?? [];

            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (is_object($entry)) {
                    $entry = json_decode(json_encode($entry, JSON_THROW_ON_ERROR), true);
                }

                if (!is_array($entry)) {
                    continue;
                }

                $metadata = $entry['socialMetadata'] ?? [];

                if (is_object($metadata)) {
                    $metadata = json_decode(json_encode($metadata, JSON_THROW_ON_ERROR), true);
                }

                if (!is_array($metadata)) {
                    continue;
                }

                $results[] = [
                    'onlineId' => isset($metadata['onlineId']) ? (string) $metadata['onlineId'] : '',
                    'accountId' => isset($metadata['accountId']) ? (string) $metadata['accountId'] : '',
                    'country' => isset($metadata['country']) ? (string) $metadata['country'] : '',
                ];
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function createGraphQlVariables(string $searchTerm): array
    {
        return [
            'age' => '69',
            'countryCode' => 'us',
            'languageCode' => 'en',
            'searchTerm' => $searchTerm,
            'domainRequests' => [
                [
                    'domain' => 'SocialAllAccounts',
                    'pagination' => [
                        'cursor' => null,
                        'pageSize' => self::RESULT_LIMIT,
                    ],
                ],
            ],
        ];
    }

    private function resolveOperationName(): string
    {
        $operationName = getenv('PSN_PLAYER_SEARCH_OPERATION_NAME');

        if (is_string($operationName) && $operationName !== '') {
            return $operationName;
        }

        return self::DEFAULT_OPERATION_NAME;
    }

    private function resolvePersistedQueryHash(): string
    {
        $hash = getenv('PSN_PLAYER_SEARCH_PERSISTED_QUERY_HASH');

        if (is_string($hash) && $hash !== '') {
            return $hash;
        }

        if (self::DEFAULT_PERSISTED_QUERY_HASH === '') {
            throw new RuntimeException('The PSN player search persisted query hash is not configured.');
        }

        return self::DEFAULT_PERSISTED_QUERY_HASH;
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
