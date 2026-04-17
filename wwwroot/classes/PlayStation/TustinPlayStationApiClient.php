<?php

declare(strict_types=1);

require_once __DIR__ . '/Contracts/PlayStationApiClientInterface.php';
require_once __DIR__ . '/Http/PlayStationHttpTransport.php';

use Tustin\PlayStation\Client;

final class TustinPlayStationApiClient implements PlayStationApiClientInterface
{
    private readonly Client $client;

    private readonly PlayStationHttpTransport $transport;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
        $this->transport = new PlayStationHttpTransport(
            requestExecutor: fn (string $path, array $query, array $headers): mixed => $this->client->get($path, $query, $headers),
            accountLookupExecutor: fn (string $accountId): mixed => $this->client->users()->find($accountId),
            userSearchExecutor: fn (string $onlineId): iterable => $this->client->users()->search($onlineId),
            maxAttempts: 2,
        );
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
        return $this->transport->lookupUserProfile($onlineId);
    }

    public function findUserByAccountId(string $accountId): object
    {
        return $this->transport->findUserByAccountId($accountId);
    }

    public function requestTrophyEndpoint(string $path, array $query = [], array $headers = []): mixed
    {
        return $this->transport->request($path, $query, $headers);
    }

    public function searchUsers(string $onlineId): iterable
    {
        return $this->transport->searchUsers($onlineId);
    }
}
