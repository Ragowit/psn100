<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/PsnPlayerSearchResult.php';
require_once __DIR__ . '/PsnPlayerSearchRateLimitException.php';

use Tustin\PlayStation\Client;

final class PsnPlayerSearchService
{
    private const RESULT_LIMIT = 50;

    private const GRAPHQL_OPERATION_CONTEXT = 'metGetContextSearchResults';

    private const GRAPHQL_OPERATION_DOMAIN = 'metGetDomainSearchResults';

    private const GRAPHQL_PERSISTED_QUERY_HASHES = [
        self::GRAPHQL_OPERATION_CONTEXT => 'ac5fb2b82c4d086ca0d272fba34418ab327a7762dd2cd620e63f175bbc5aff10',
        self::GRAPHQL_OPERATION_DOMAIN => '23ece284bf8bdc50bfa30a4d97fd4d733e723beb7a42dff8c1ee883f8461a2e1',
    ];

    private const GRAPHQL_HEADERS = [
        'apollographql-client-name' => 'PlayStationApp-Android',
        'content-type' => 'application/json',
    ];

    private const GRAPHQL_SEARCH_CONTEXT = 'MobileUniversalSearchSocial';

    private const GRAPHQL_SEARCH_LOCALE = 'en-US';

    private const GRAPHQL_SEARCH_DOMAIN = 'SocialAllAccounts';

    private const GRAPHQL_PAGE_SIZE = 20;

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

        try {
            return $this->searchPlayersUsingGraphql($client, $normalizedPlayerName);
        } catch (Throwable $exception) {
            $rateLimitException = $this->createRateLimitException($exception);

            if ($rateLimitException !== null) {
                throw $rateLimitException;
            }

            throw $exception;
        }
    }

    /**
     * @return list<PsnPlayerSearchResult>
     */
    private function searchPlayersUsingGraphql(object $client, string $searchTerm): array
    {
        $players = [];

        $domainResponse = $this->performContextSearch($client, $searchTerm);

        if ($domainResponse === null) {
            return [];
        }

        $this->appendPlayerEntries($players, $domainResponse->searchResults ?? null);

        $nextCursor = $this->extractStringProperty($domainResponse, 'next');
        $pageOffset = count($players);

        while ($nextCursor !== '' && count($players) < self::RESULT_LIMIT) {
            $domainResponse = $this->performDomainSearch($client, $searchTerm, $nextCursor, $pageOffset);

            if ($domainResponse === null) {
                break;
            }

            $limitReached = $this->appendPlayerEntries($players, $domainResponse->searchResults ?? null);

            if ($limitReached) {
                break;
            }

            $nextCursor = $this->extractStringProperty($domainResponse, 'next');
            $pageOffset = count($players);
        }

        return $this->hydratePlayerSearchResults($client, $players);
    }

    private function performContextSearch(object $client, string $searchTerm): ?object
    {
        $response = $this->executeGraphqlRequest(
            $client,
            self::GRAPHQL_OPERATION_CONTEXT,
            [
                'searchTerm' => $searchTerm,
                'searchContext' => self::GRAPHQL_SEARCH_CONTEXT,
                'displayTitleLocale' => self::GRAPHQL_SEARCH_LOCALE,
            ]
        );

        if (!is_object($response)) {
            return null;
        }

        $data = $response->data ?? null;

        if (!is_object($data)) {
            return null;
        }

        $contextSearch = $data->universalContextSearch ?? null;

        if (!is_object($contextSearch)) {
            return null;
        }

        $results = $contextSearch->results ?? null;

        if (!is_array($results)) {
            return null;
        }

        foreach ($results as $domainResponse) {
            if (!is_object($domainResponse)) {
                continue;
            }

            if ($this->extractStringProperty($domainResponse, 'domain') !== self::GRAPHQL_SEARCH_DOMAIN) {
                continue;
            }

            return $domainResponse;
        }

        return null;
    }

    private function performDomainSearch(
        object $client,
        string $searchTerm,
        string $nextCursor,
        int $pageOffset
    ): ?object {
        if ($nextCursor === '') {
            return null;
        }

        $remaining = self::RESULT_LIMIT - $pageOffset;

        if ($remaining <= 0) {
            return null;
        }

        $pageSize = max(1, min(self::GRAPHQL_PAGE_SIZE, $remaining));

        $response = $this->executeGraphqlRequest(
            $client,
            self::GRAPHQL_OPERATION_DOMAIN,
            [
                'searchTerm' => $searchTerm,
                'searchDomain' => self::GRAPHQL_SEARCH_DOMAIN,
                'nextCursor' => $nextCursor,
                'pageSize' => $pageSize,
                'pageOffset' => $pageOffset,
            ]
        );

        if (!is_object($response)) {
            return null;
        }

        $data = $response->data ?? null;

        if (!is_object($data)) {
            return null;
        }

        $domainSearch = $data->universalDomainSearch ?? null;

        if (!is_object($domainSearch)) {
            return null;
        }

        if ($this->extractStringProperty($domainSearch, 'domain') !== self::GRAPHQL_SEARCH_DOMAIN) {
            return null;
        }

        return $domainSearch;
    }

    private function executeGraphqlRequest(object $client, string $operation, array $variables): ?object
    {
        $hash = self::GRAPHQL_PERSISTED_QUERY_HASHES[$operation] ?? null;

        if ($hash === null) {
            throw new RuntimeException('Unsupported GraphQL operation: ' . $operation);
        }

        $variablesJson = $this->encodeJson($variables);
        $extensionsJson = $this->encodeJson([
            'persistedQuery' => [
                'version' => 1,
                'sha256Hash' => $hash,
            ],
        ]);

        if (!method_exists($client, 'get')) {
            throw new RuntimeException('The PlayStation client does not support GraphQL requests.');
        }

        $response = $client->get(
            'graphql/v1/op',
            [
                'operationName' => $operation,
                'variables' => $variablesJson,
                'extensions' => $extensionsJson,
            ],
            self::GRAPHQL_HEADERS
        );

        if (!is_object($response)) {
            return null;
        }

        if (property_exists($response, 'errors') && is_array($response->errors) && $response->errors !== []) {
            throw new RuntimeException('GraphQL query returned errors for operation: ' . $operation);
        }

        return $response;
    }

    private function encodeJson(array $value): string
    {
        $encoded = json_encode($value);

        if (!is_string($encoded)) {
            throw new RuntimeException('Failed to encode GraphQL payload.');
        }

        return $encoded;
    }

    /**
     * @param list<array{onlineId: string, accountId: string}> $players
     */
    private function appendPlayerEntries(array &$players, mixed $searchResults): bool
    {
        if (!is_array($searchResults)) {
            return count($players) >= self::RESULT_LIMIT;
        }

        foreach ($searchResults as $item) {
            if (!is_object($item)) {
                continue;
            }

            $player = $item->result ?? null;

            if (!is_object($player)) {
                continue;
            }

            $type = $this->extractStringProperty($player, '__typename');

            if ($type !== '' && $type !== 'Player') {
                continue;
            }

            $onlineId = $this->extractStringProperty($player, 'onlineId');
            $accountId = $this->extractStringProperty($player, 'accountId');

            if ($onlineId === '' && $accountId === '') {
                continue;
            }

            $players[] = [
                'onlineId' => $onlineId,
                'accountId' => $accountId,
            ];

            if (count($players) >= self::RESULT_LIMIT) {
                return true;
            }
        }

        return count($players) >= self::RESULT_LIMIT;
    }

    /**
     * @param list<array{onlineId: string, accountId: string}> $players
     * @return list<PsnPlayerSearchResult>
     */
    private function hydratePlayerSearchResults(object $client, array $players): array
    {
        $results = [];

        foreach ($players as $player) {
            $onlineId = $player['onlineId'] ?? '';
            $accountId = $player['accountId'] ?? '';
            $languages = '';

            if ($accountId !== '') {
                $languages = $this->fetchPlayerLanguages($client, $accountId);
            }

            $results[] = new PsnPlayerSearchResult($onlineId, $accountId, $languages);
        }

        return $results;
    }

    private function extractStringProperty(object $source, string $property): string
    {
        if (!property_exists($source, $property)) {
            return '';
        }

        $value = $source->{$property};

        if (is_string($value)) {
            return trim($value);
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return '';
    }

    private function fetchPlayerLanguages(object $client, string $accountId): string
    {
        try {
            $profile = $this->executeUserProfileRequest($client, $accountId);
        } catch (Throwable) {
            return '';
        }

        if (!is_object($profile)) {
            return '';
        }

        $languages = $profile->languages ?? null;

        if (!is_array($languages)) {
            return '';
        }

        $normalized = [];

        foreach ($languages as $language) {
            if (!is_string($language)) {
                continue;
            }

            $language = trim($language);

            if ($language === '') {
                continue;
            }

            if (!in_array($language, $normalized, true)) {
                $normalized[] = $language;
            }
        }

        return implode(', ', $normalized);
    }

    private function executeUserProfileRequest(object $client, string $accountId): ?object
    {
        if (!method_exists($client, 'get')) {
            throw new RuntimeException('The PlayStation client does not support profile requests.');
        }

        $path = sprintf(
            'userProfile/v1/internal/users/%s/profiles',
            rawurlencode($accountId)
        );

        return $client->get($path, [], ['content-type' => 'application/json']);
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
