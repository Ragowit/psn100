<?php

declare(strict_types=1);

namespace PsnApi;

final class UsersApi
{
    private const GRAPHQL_OPERATION = 'metGetContextSearchResults';

    private const GRAPHQL_HASH = 'ac5fb2b82c4d086ca0d272fba34418ab327a7762dd2cd620e63f175bbc5aff10';

    private HttpClient $httpClient;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @return iterable<UserSearchResult>
     */
    public function search(string $query): iterable
    {
        $normalizedQuery = trim($query);
        if ($normalizedQuery === '') {
            return [];
        }

        $payload = [
            'operationName' => self::GRAPHQL_OPERATION,
            'variables' => [
				'searchTerm' => $normalizedQuery,
				'searchContext' => 'MobileUniversalSearchSocial',
				'displayTitleLocale' => 'en-US',
			],
            'extensions' => [
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => self::GRAPHQL_HASH,
                ],
            ],
        ];

        $response = $this->httpClient->post(
            'graphql/v1/op',
            [],
            [
                'apollographql-client-name' => 'PlayStationApp-Android',
                'apollographql-client-version' => '1.0.0',
                'Accept' => 'application/json',
            ],
            null,
            $this->encodeJson($payload)
        );

        $payload = $response->getJson();
        if (!is_object($payload) || !isset($payload->data) || !is_object($payload->data)) {
            return [];
        }

        $searchData = $payload->data->universalContextSearch ?? null;
        if (!is_object($searchData) || !isset($searchData->results) || !is_array($searchData->results)) {
            return [];
        }

        $results = [];
        foreach ($searchData->results as $domainResult) {
            if (!is_object($domainResult)) {
                continue;
            }

            if ((string) ($domainResult->domain ?? '') !== 'SocialAllAccounts') {
                continue;
            }

            if (!isset($domainResult->searchResults) || !is_array($domainResult->searchResults)) {
                continue;
            }

            foreach ($domainResult->searchResults as $searchResult) {
                if (!is_object($searchResult) || !isset($searchResult->result) || !is_object($searchResult->result)) {
                    continue;
                }

                $player = $searchResult->result;
                $onlineId = isset($player->onlineId) && is_scalar($player->onlineId)
                    ? (string) $player->onlineId
                    : '';
                $accountId = isset($player->accountId) && is_scalar($player->accountId)
                    ? (string) $player->accountId
                    : '';

                if ($onlineId === '' || $accountId === '') {
                    continue;
                }

                $country = $this->extractCountry($player);

                $metadata = (object) [
                    'onlineId' => $onlineId,
                    'accountId' => $accountId,
                    'country' => $country,
                    'avatarUrl' => isset($player->avatarUrl) ? (string) $player->avatarUrl : null,
                    'profilePicUrl' => isset($player->profilePicUrl) ? (string) $player->profilePicUrl : null,
                    'avatars' => $this->buildAvatarMetadata($player),
                    'isPlus' => $this->extractPlusStatus($player),
                    'aboutMe' => $this->extractAboutMe($player),
                ];

                $results[] = new UserSearchResult(
                    $this->httpClient,
                    $onlineId,
                    $accountId,
                    $country,
                    $metadata
                );
            }
        }

        return $results;
    }

    public function find(string $accountId): UserProfile
    {
        return new UserProfile($this->httpClient, $accountId);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encodeJson(array $data): string
    {
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode request payload.');
        }

        return $encoded;
    }

    /**
     * @return list<object>
     */
    private function buildAvatarMetadata(object $player): array
    {
        $avatars = [];

        $largeAvatar = isset($player->avatarUrl) && is_scalar($player->avatarUrl)
            ? (string) $player->avatarUrl
            : null;
        $profileAvatar = isset($player->profilePicUrl) && is_scalar($player->profilePicUrl)
            ? (string) $player->profilePicUrl
            : null;

        if (is_string($largeAvatar) && $largeAvatar !== '') {
            foreach (['xl', 'l'] as $size) {
                $avatars[] = (object) ['size' => $size, 'url' => $largeAvatar];
            }
        }

        if (is_string($profileAvatar) && $profileAvatar !== '') {
            foreach (['m', 's'] as $size) {
                $avatars[] = (object) ['size' => $size, 'url' => $profileAvatar];
            }
        }

        if ($avatars === [] && is_string($largeAvatar) && $largeAvatar !== '') {
            foreach (['xl', 'l', 'm', 's'] as $size) {
                $avatars[] = (object) ['size' => $size, 'url' => $largeAvatar];
            }
        }

        if ($avatars === [] && is_string($profileAvatar) && $profileAvatar !== '') {
            foreach (['xl', 'l', 'm', 's'] as $size) {
                $avatars[] = (object) ['size' => $size, 'url' => $profileAvatar];
            }
        }

        return $avatars;
    }

    private function extractCountry(object $player): string
    {
        $objects = [];

        if (isset($player->socialMetadata) && is_object($player->socialMetadata)) {
            if (isset($player->socialMetadata->personalDetail) && is_object($player->socialMetadata->personalDetail)) {
                $personalDetail = $player->socialMetadata->personalDetail;

                $objects[] = $personalDetail;

                foreach ([
                    'country',
                    'residenceCountry',
                    'location',
                ] as $nestedProperty) {
                    if (isset($personalDetail->{$nestedProperty}) && is_object($personalDetail->{$nestedProperty})) {
                        $objects[] = $personalDetail->{$nestedProperty};
                    }
                }
            }

            $objects[] = $player->socialMetadata;
        }

        $objects[] = $player;

        $countrySources = [
            'countryAlphaTwo',
            'countryAlpha2',
            'countryCode',
            'country',
            'residenceCountry',
            'accountCountry',
            'region',
        ];

        foreach ($objects as $object) {
            foreach ($countrySources as $source) {
                if (!isset($object->{$source})) {
                    continue;
                }

                $country = $this->normalizeCountry($object->{$source});

                if ($country !== null) {
                    return $country;
                }
            }
        }

        return '';
    }

    private function extractPlusStatus(object $player): ?bool
    {
        if (isset($player->isPlus) && is_bool($player->isPlus)) {
            return $player->isPlus;
        }

        if (isset($player->isPsPlus) && is_bool($player->isPsPlus)) {
            return $player->isPsPlus;
        }

        return null;
    }

    private function extractAboutMe(object $player): ?string
    {
        if (isset($player->aboutMe) && is_scalar($player->aboutMe)) {
            return (string) $player->aboutMe;
        }

        if (isset($player->personalDetail) && is_object($player->personalDetail) && isset($player->personalDetail->aboutMe) && is_scalar($player->personalDetail->aboutMe)) {
            return (string) $player->personalDetail->aboutMe;
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

        if (is_object($value)) {
            foreach ([
                'alphaTwoCode',
                'alpha2',
                'alphaTwo',
                'isoCode',
                'code',
                'value',
            ] as $property) {
                if (!isset($value->{$property})) {
                    continue;
                }

                $country = $this->normalizeCountry($value->{$property});

                if ($country !== null) {
                    return $country;
                }
            }

            return null;
        }

        if (is_array($value)) {
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
