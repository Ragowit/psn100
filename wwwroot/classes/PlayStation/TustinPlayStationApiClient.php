<?php

declare(strict_types=1);

require_once __DIR__ . '/Contracts/PlayStationApiClientInterface.php';

use Tustin\PlayStation\Client;

final class TustinPlayStationApiClient implements PlayStationApiClientInterface
{
    private readonly Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public function loginWithNpsso(string $npsso): void
    {
        $this->client->loginWithNpsso($npsso);
    }

    public function acquireAccessToken(): ?string
    {
        if (method_exists($this->client, 'accessToken')) {
            $token = $this->client->accessToken();

            return is_string($token) && $token !== '' ? $token : null;
        }

        return null;
    }

    public function refreshAccessToken(): void
    {
        if (method_exists($this->client, 'refreshToken')) {
            $this->client->refreshToken();

            return;
        }

        throw new RuntimeException('The PlayStation client does not support token refresh.');
    }

    public function lookupProfileByOnlineId(string $onlineId): mixed
    {
        $path = sprintf(
            'https://us-prof.np.community.playstation.net/userProfile/v1/users/%s/profile2',
            rawurlencode($onlineId)
        );

        return $this->client->get(
            $path,
            ['fields' => 'accountId,onlineId,currentOnlineId,npId'],
            ['content-type' => 'application/json']
        );
    }

    public function findUserByAccountId(string $accountId): object
    {
        return $this->client->users()->find($accountId);
    }

    public function requestTrophyEndpoint(string $path, array $query = [], array $headers = []): mixed
    {
        return $this->client->get($path, $query, $headers);
    }

    public function searchUsers(string $onlineId): iterable
    {
        return $this->client->users()->search($onlineId);
    }
}
