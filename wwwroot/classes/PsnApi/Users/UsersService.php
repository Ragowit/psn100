<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Users;

use Achievements\PsnApi\Client;
use Achievements\PsnApi\Exceptions\ApiException;
use Achievements\PsnApi\Exceptions\NotFoundException;

final class UsersService
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @return iterable<UserSearchResult>
     */
    public function search(string $query): iterable
    {
        $trimmed = trim($query);

        if ($trimmed === '') {
            return [];
        }

        $authorizationHeaders = $this->client->authorizationHeaders();

        $response = $this->client->requestJson(
            'POST',
            'https://m.np.playstation.com/api/search/v1/universalSearch',
            [
                'searchTerm' => $trimmed,
                'domainRequests' => [
                    ['domain' => 'SocialAllAccounts'],
                ],
            ],
            $authorizationHeaders,
            true
        );

        $results = [];

        if (!isset($response['domainResponses']) || !is_array($response['domainResponses'])) {
            return $results;
        }

        foreach ($response['domainResponses'] as $domainResponse) {
            if (!isset($domainResponse['domain']) || $domainResponse['domain'] !== 'SocialAllAccounts') {
                continue;
            }

            if (!isset($domainResponse['results']) || !is_array($domainResponse['results'])) {
                continue;
            }

            foreach ($domainResponse['results'] as $result) {
                if (!isset($result['socialMetadata']) || !is_array($result['socialMetadata'])) {
                    continue;
                }

                $metadata = $result['socialMetadata'];
                $results[] = new UserSearchResult(
                    (string) ($metadata['onlineId'] ?? ''),
                    (string) ($metadata['accountId'] ?? ''),
                    (string) ($metadata['country'] ?? '')
                );

                if (count($results) >= 50) {
                    break 2;
                }
            }
        }

        return $results;
    }

    public function find(string $accountId): User
    {
        $authorizationHeaders = $this->client->authorizationHeaders();

        try {
            $profile = $this->client->requestJson(
                'GET',
                sprintf('https://m.np.playstation.com/api/userProfile/v1/internal/users/%s/profiles', rawurlencode($accountId)),
                null,
                $authorizationHeaders
            );
        } catch (ApiException $exception) {
            if (in_array($exception->getCode(), [403, 404], true)) {
                throw new NotFoundException('User not found.', 0, $exception);
            }

            throw $exception;
        }

        if (!isset($profile['onlineId'])) {
            throw new NotFoundException('User not found.');
        }

        $avatarUrls = [];
        if (isset($profile['avatars']) && is_array($profile['avatars'])) {
            foreach ($profile['avatars'] as $avatar) {
                if (!isset($avatar['size'], $avatar['url'])) {
                    continue;
                }

                $avatarUrls[(string) $avatar['size']] = (string) $avatar['url'];
            }
        }

        try {
            $summaryResponse = $this->client->requestJson(
                'GET',
                sprintf('https://m.np.playstation.com/api/trophy/v1/users/%s/trophySummary', rawurlencode($accountId)),
                null,
                $authorizationHeaders
            );
        } catch (ApiException $exception) {
            if (in_array($exception->getCode(), [403, 404], true)) {
                throw new NotFoundException('User trophy summary not accessible.', 0, $exception);
            }

            throw $exception;
        }

        $summary = $this->createSummary($summaryResponse);

        return new User(
            $this->client,
            $accountId,
            (string) $profile['onlineId'],
            (string) ($profile['aboutMe'] ?? ''),
            $avatarUrls,
            isset($profile['languages']) && is_array($profile['languages']) ? array_map('strval', $profile['languages']) : [],
            (bool) ($profile['isPlus'] ?? false),
            (bool) ($profile['isOfficiallyVerified'] ?? false),
            $summary
        );
    }

    /**
     * @param array<string, mixed> $summaryResponse
     */
    private function createSummary(array $summaryResponse): TrophySummary
    {
        $level = (int) ($summaryResponse['trophyLevel'] ?? 0);
        $progress = (int) ($summaryResponse['progress'] ?? 0);

        $earned = $summaryResponse['earnedTrophies'] ?? [];

        if (!is_array($earned)) {
            $earned = [];
        }

        return new TrophySummary(
            $level,
            $progress,
            [
                'platinum' => (int) ($earned['platinum'] ?? 0),
                'gold' => (int) ($earned['gold'] ?? 0),
                'silver' => (int) ($earned['silver'] ?? 0),
                'bronze' => (int) ($earned['bronze'] ?? 0),
            ]
        );
    }
}
