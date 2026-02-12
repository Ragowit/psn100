<?php

declare(strict_types=1);

final class PlayStationGraphqlPlayerSearch
{
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

    private object $client;

    private int $resultLimit;

    public function __construct(object $client, int $resultLimit = 50)
    {
        $this->client = $client;
        $this->resultLimit = max(1, $resultLimit);
    }

    /**
     * @return list<array{onlineId: string, accountId: string}>
     */
    public function search(string $searchTerm, ?int $limit = null): array
    {
        $normalized = trim($searchTerm);

        if ($normalized === '') {
            return [];
        }

        $limit = $limit !== null ? max(1, $limit) : $this->resultLimit;
        $players = [];

        $contextResponse = $this->performContextSearch($normalized);

        if ($contextResponse === null) {
            return [];
        }

        $this->appendPlayerEntries($players, $contextResponse->searchResults ?? null, $limit);

        $nextCursor = $this->extractStringProperty($contextResponse, 'next');
        $pageOffset = count($players);

        while ($nextCursor !== '' && count($players) < $limit) {
            $domainResponse = $this->performDomainSearch($normalized, $nextCursor, $pageOffset, $limit);

            if ($domainResponse === null) {
                break;
            }

            $limitReached = $this->appendPlayerEntries($players, $domainResponse->searchResults ?? null, $limit);

            if ($limitReached) {
                break;
            }

            $nextCursor = $this->extractStringProperty($domainResponse, 'next');
            $pageOffset = count($players);
        }

        return $players;
    }

    /**
     * @return array{onlineId: string, accountId: string}|null
     */
    public function findExactPlayer(string $onlineId): ?array
    {
        $normalizedId = strtolower(trim($onlineId));

        if ($normalizedId === '') {
            return null;
        }

        foreach ($this->search($onlineId) as $player) {
            $playerOnlineId = strtolower((string) ($player['onlineId'] ?? ''));

            if ($playerOnlineId === $normalizedId) {
                return $player;
            }
        }

        return null;
    }

    private function performContextSearch(string $searchTerm): ?object
    {
        $response = $this->executeGraphqlRequest(
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
        string $searchTerm,
        string $nextCursor,
        int $pageOffset,
        int $limit
    ): ?object {
        if ($nextCursor === '') {
            return null;
        }

        $remaining = $limit - $pageOffset;

        if ($remaining <= 0) {
            return null;
        }

        $pageSize = max(1, min(self::GRAPHQL_PAGE_SIZE, $remaining));

        $response = $this->executeGraphqlRequest(
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

    private function executeGraphqlRequest(string $operation, array $variables): ?object
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

        if (!method_exists($this->client, 'get')) {
            throw new RuntimeException('The PlayStation client does not support GraphQL requests.');
        }

        $response = $this->client->get(
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
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Failed to encode GraphQL payload.');
        }
    }

    /**
     * @param list<array{onlineId: string, accountId: string}> $players
     */
    private function appendPlayerEntries(array &$players, mixed $searchResults, int $limit): bool
    {
        if (!is_array($searchResults)) {
            return count($players) >= $limit;
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

            if (count($players) >= $limit) {
                return true;
            }
        }

        return count($players) >= $limit;
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
}
