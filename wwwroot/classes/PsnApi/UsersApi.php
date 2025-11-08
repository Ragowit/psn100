<?php

declare(strict_types=1);

namespace PsnApi;

final class UsersApi
{
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
            'age' => '69',
            'countryCode' => 'us',
            'domainRequests' => [
                [
                    'domain' => 'SocialAllAccounts',
                    'pagination' => [
                        'cursor' => '',
                        'pageSize' => '50',
                    ],
                ],
            ],
            'languageCode' => 'en',
            'searchTerm' => $normalizedQuery,
        ];

        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encodedPayload === false) {
            throw new \RuntimeException('Failed to encode search payload.');
        }

        $response = $this->httpClient->post(
            'search/v1/universalSearch',
            [],
            [],
            null,
            $encodedPayload
        );

        $data = $response->getJson();
        if (!is_object($data) || !isset($data->domainResponses[0])) {
            return [];
        }

        $domainResponse = $data->domainResponses[0];
        if (!isset($domainResponse->results) || !is_array($domainResponse->results)) {
            return [];
        }

        $results = [];
        foreach ($domainResponse->results as $result) {
            if (!isset($result->socialMetadata)) {
                continue;
            }

            $metadata = $result->socialMetadata;
            $results[] = new UserSearchResult(
                $this->httpClient,
                (string) ($metadata->onlineId ?? ''),
                (string) ($metadata->accountId ?? ''),
                (string) ($metadata->country ?? ''),
                $metadata
            );
        }

        return $results;
    }

    public function find(string $accountId): UserProfile
    {
        return new UserProfile($this->httpClient, $accountId);
    }
}
