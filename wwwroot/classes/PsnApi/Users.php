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

        $results = [];
        foreach ($response['domainResponses'] ?? [] as $domainResponse) {
            if (!isset($domainResponse['results']) || !is_array($domainResponse['results'])) {
                continue;
            }

            foreach ($domainResponse['results'] as $result) {
                if (!is_array($result) || !isset($result['socialMetadata']) || !is_array($result['socialMetadata'])) {
                    continue;
                }

                $metadata = $result['socialMetadata'];
                $accountId = isset($metadata['accountId']) ? (string) $metadata['accountId'] : '';
                if ($accountId === '') {
                    continue;
                }

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
}
