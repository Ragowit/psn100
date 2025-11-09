<?php

declare(strict_types=1);

namespace PsnApi;

use ArrayIterator;

final class Users
{
    private const GRAPHQL_OPERATION = 'metGetContextSearchResults';
    private const GRAPHQL_HASH = 'ac5fb2b82c4d086ca0d272fba34418ab327a7762dd2cd620e63f175bbc5aff10';
    private const GRAPHQL_CLIENT_NAME = 'PlayStationApp-Android';
    private const GRAPHQL_CLIENT_VERSION = '1.0.0';
    private const GRAPHQL_SEARCH_CONTEXT = 'MobileUniversalSearchSocial';
    private const GRAPHQL_LOCALE = 'en-US';

    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @return iterable<User>
     */
    public function search(string $query): iterable
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return [];
        }

        $response = $this->client->postJson(
            '/api/graphql/v1/op',
            [
                'operationName' => self::GRAPHQL_OPERATION,
                'variables' => [
                    'searchTerm' => $trimmed,
                    'searchContext' => self::GRAPHQL_SEARCH_CONTEXT,
                    'displayTitleLocale' => self::GRAPHQL_LOCALE,
                ],
                'extensions' => [
                    'persistedQuery' => [
                        'version' => 1,
                        'sha256Hash' => self::GRAPHQL_HASH,
                    ],
                ],
            ],
            [],
            [
                'apollographql-client-name: ' . self::GRAPHQL_CLIENT_NAME,
                'apollographql-client-version: ' . self::GRAPHQL_CLIENT_VERSION,
            ]
        );

        $results = [];
        $domainResponses = $response['data']['universalContextSearch']['results'] ?? [];
        if (!is_array($domainResponses)) {
            return [];
        }

        foreach ($domainResponses as $domainResponse) {
            if (!is_array($domainResponse)) {
                continue;
            }

            if ((string) ($domainResponse['domain'] ?? '') !== 'SocialAllAccounts') {
                continue;
            }

            $searchResults = $domainResponse['searchResults'] ?? [];
            if (!is_array($searchResults)) {
                continue;
            }

            foreach ($searchResults as $result) {
                if (!is_array($result) || !isset($result['result']) || !is_array($result['result'])) {
                    continue;
                }

                $player = $result['result'];
                if (!isset($player['onlineId']) || !is_scalar($player['onlineId'])) {
                    continue;
                }
                $accountId = isset($player['accountId']) ? (string) $player['accountId'] : '';
                if ($accountId === '') {
                    continue;
                }

                $metadata = $this->buildMetadata($player);
                $results[] = User::fromSearchMetadata(
                    $this->client,
                    $accountId,
                    $metadata
                );
            }
        }

        return new ArrayIterator($results);
    }

    public function find(string $accountId): User
    {
        return User::fromAccountId($this->client, $accountId);
    }

    /**
     * @param array<string, mixed> $player
     * @return array<string, mixed>
     */
    private function buildMetadata(array $player): array
    {
        $onlineId = isset($player['onlineId']) ? (string) $player['onlineId'] : '';

        return [
            'onlineId' => $onlineId,
            'accountId' => isset($player['accountId']) ? (string) $player['accountId'] : null,
            'country' => $this->extractCountry($player),
            'avatars' => $this->buildAvatarMetadata($player),
            'isPlus' => $this->extractPlusStatus($player),
            'aboutMe' => $this->extractAboutMe($player),
        ];
    }

    /**
     * @param array<string, mixed> $player
     * @return list<array{size:string,url:string}>
     */
    private function buildAvatarMetadata(array $player): array
    {
        $avatars = [];

        $largeAvatar = isset($player['avatarUrl']) && is_scalar($player['avatarUrl'])
            ? (string) $player['avatarUrl']
            : null;
        $profileAvatar = isset($player['profilePicUrl']) && is_scalar($player['profilePicUrl'])
            ? (string) $player['profilePicUrl']
            : null;

        if (is_string($largeAvatar) && $largeAvatar !== '') {
            foreach (['xl', 'l'] as $size) {
                $avatars[] = ['size' => $size, 'url' => $largeAvatar];
            }
        }

        if (is_string($profileAvatar) && $profileAvatar !== '') {
            foreach (['m', 's'] as $size) {
                $avatars[] = ['size' => $size, 'url' => $profileAvatar];
            }
        }

        if ($avatars === [] && is_string($largeAvatar) && $largeAvatar !== '') {
            foreach (['xl', 'l', 'm', 's'] as $size) {
                $avatars[] = ['size' => $size, 'url' => $largeAvatar];
            }
        }

        if ($avatars === [] && is_string($profileAvatar) && $profileAvatar !== '') {
            foreach (['xl', 'l', 'm', 's'] as $size) {
                $avatars[] = ['size' => $size, 'url' => $profileAvatar];
            }
        }

        return $avatars;
    }

    /**
     * @param array<string, mixed> $player
     */
    private function extractCountry(array $player): string
    {
        $sources = [];

        if (isset($player['socialMetadata']) && is_array($player['socialMetadata'])) {
            $socialMetadata = $player['socialMetadata'];
            if (isset($socialMetadata['personalDetail']) && is_array($socialMetadata['personalDetail'])) {
                $sources[] = $socialMetadata['personalDetail'];
            }

            $sources[] = $socialMetadata;
        }

        $sources[] = $player;

        $keys = [
            'countryAlphaTwo',
            'countryAlpha2',
            'countryCode',
            'country',
            'accountCountry',
            'region',
        ];

        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }

            foreach ($keys as $key) {
                if (!array_key_exists($key, $source)) {
                    continue;
                }

                $country = $this->normalizeCountry($source[$key]);
                if ($country !== null) {
                    return $country;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $player
     */
    private function extractPlusStatus(array $player): ?bool
    {
        if (isset($player['isPlus']) && is_bool($player['isPlus'])) {
            return $player['isPlus'];
        }

        if (isset($player['isPsPlus']) && is_bool($player['isPsPlus'])) {
            return $player['isPsPlus'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $player
     */
    private function extractAboutMe(array $player): ?string
    {
        if (isset($player['aboutMe']) && is_scalar($player['aboutMe'])) {
            return (string) $player['aboutMe'];
        }

        if (
            isset($player['personalDetail'])
            && is_array($player['personalDetail'])
            && isset($player['personalDetail']['aboutMe'])
            && is_scalar($player['personalDetail']['aboutMe'])
        ) {
            return (string) $player['personalDetail']['aboutMe'];
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private function normalizeCountry($value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $country = strtoupper(trim((string) $value));

            if ($country === '') {
                return null;
            }

            if (preg_match('/^[A-Z]{2}$/', $country) === 1) {
                return $country;
            }

            if (preg_match('/^[A-Z]{3}$/', $country) === 1) {
                return $country;
            }

            if (preg_match('/^[A-Z]{2}_[A-Z]{2}$/', $country) === 1) {
                return substr($country, -2);
            }

            return $country;
        }

        if (is_array($value)) {
            foreach ([
                'alphaTwoCode',
                'alpha2',
                'alphaTwo',
                'isoCode',
                'code',
                'value',
            ] as $property) {
                if (!array_key_exists($property, $value)) {
                    continue;
                }

                $country = $this->normalizeCountry($value[$property]);
                if ($country !== null) {
                    return $country;
                }
            }

            foreach ($value as $item) {
                $country = $this->normalizeCountry($item);
                if ($country !== null) {
                    return $country;
                }
            }
        }

        return null;
    }
}
