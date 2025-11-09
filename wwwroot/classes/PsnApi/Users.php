<?php

declare(strict_types=1);

namespace PsnApi;

use ArrayIterator;

final class Users
{
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

        $response = $this->client->get('/api/search/v1/universalSearch', [
            'searchDomains' => 'SocialAllAccounts',
            'countryCode' => 'us',
            'languageCode' => 'en',
            'age' => '69',
            'pageSize' => '50',
            'searchTerm' => $trimmed,
        ]);

        $domainResponses = $response['domainResponses'] ?? [];
        if (!is_array($domainResponses)) {
            return [];
        }

        $results = [];
        foreach ($domainResponses as $domainResponse) {
            if (!is_array($domainResponse)) {
                continue;
            }

            $searchResults = $domainResponse['results'] ?? [];
            if (!is_array($searchResults)) {
                continue;
            }

            foreach ($searchResults as $result) {
                if (!is_array($result)) {
                    continue;
                }

                $metadata = $result['socialMetadata'] ?? [];
                if (!is_array($metadata)) {
                    continue;
                }

                $accountId = isset($metadata['accountId']) ? (string) $metadata['accountId'] : '';
                if ($accountId === '') {
                    continue;
                }

                $results[] = User::fromSearchMetadata(
                    $this->client,
                    $accountId,
                    $this->buildMetadata($metadata)
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
     * @param array<string, mixed> $socialMetadata
     * @return array<string, mixed>
     */
    private function buildMetadata(array $socialMetadata): array
    {
        return [
            'onlineId' => isset($socialMetadata['onlineId']) ? (string) $socialMetadata['onlineId'] : '',
            'accountId' => isset($socialMetadata['accountId']) ? (string) $socialMetadata['accountId'] : null,
            'country' => isset($socialMetadata['country']) ? (string) $socialMetadata['country'] : null,
        ];
    }
}
