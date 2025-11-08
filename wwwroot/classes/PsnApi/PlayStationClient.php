<?php

declare(strict_types=1);

namespace PsnApi;

final class PlayStationClient
{
    private const AUTH_BASE_URI = 'https://ca.account.sony.com/api/';

    private const BASE_URI = 'https://m.np.playstation.com/api/';

    private HttpClient $httpClient;

    private HttpClient $authClient;

    private ?OAuthToken $accessToken = null;

    private ?OAuthToken $refreshToken = null;

    public function __construct(?HttpClient $httpClient = null, ?HttpClient $authClient = null)
    {
        $defaultHeaders = [
            'User-Agent' => 'PlayStation/21090100 CFNetwork/1126 Darwin/19.5.0',
            'Accept-Language' => 'en-US',
        ];

        $this->httpClient = $httpClient ?? new HttpClient(self::BASE_URI, $defaultHeaders);
        $this->authClient = $authClient ?? new HttpClient(self::AUTH_BASE_URI, $defaultHeaders);
    }

    public function loginWithNpsso(string $npsso): void
    {
        $authorizeResponse = $this->authClient->get(
            'authz/v3/oauth/authorize',
            [
                'access_type' => 'offline',
                'client_id' => '09515159-7237-4370-9b40-3806e67c0891',
                'redirect_uri' => 'com.scee.psxandroid.scecompcall://redirect',
                'response_type' => 'code',
                'scope' => 'psn:mobile.v2.core psn:clientapp',
                'smcid' => 'psapp:settings-entrance',
            ],
            [
                'Cookie' => 'npsso=' . $npsso,
            ]
        );

        if ($authorizeResponse->getStatusCode() !== 302) {
            throw new \RuntimeException('Unexpected response from authorization endpoint.');
        }

        $location = $authorizeResponse->getHeaderLine('Location');
        if ($location === '') {
            throw new \RuntimeException('Authorization redirect did not contain a location header.');
        }

        $queryString = parse_url($location, PHP_URL_QUERY);
        if (!is_string($queryString)) {
            throw new \RuntimeException('Authorization redirect missing query string.');
        }

        parse_str($queryString, $params);
        if (!isset($params['code']) || !is_string($params['code'])) {
            throw new \RuntimeException('Authorization redirect missing authorization code.');
        }

        $tokenResponse = $this->authClient->post(
            'authz/v3/oauth/token',
            [],
            [
                'Cookie' => 'npsso=' . $npsso,
                'Authorization' => 'Basic MDk1MTUxNTktNzIzNy00MzcwLTliNDAtMzgwNmU2N2MwODkxOnVjUGprYTV0bnRCMktxc1A=',
            ],
            [
                'smcid' => 'psapp:settings-entrance',
                'access_type' => 'offline',
                'code' => $params['code'],
                'grant_type' => 'authorization_code',
                'redirect_uri' => 'com.scee.psxandroid.scecompcall://redirect',
                'scope' => 'psn:mobile.v2.core psn:clientapp',
                'token_format' => 'jwt',
            ]
        );

        $this->finalizeLogin($tokenResponse);
    }

    public function loginWithRefreshToken(string $refreshToken): void
    {
        $tokenResponse = $this->authClient->post(
            'authz/v3/oauth/token',
            [],
            [
                'Authorization' => 'Basic MDk1MTUxNTktNzIzNy00MzcwLTliNDAtMzgwNmU2N2MwODkxOnVjUGprYTV0bnRCMktxc1A=',
            ],
            [
                'scope' => 'psn:mobile.v2.core psn:clientapp',
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
                'token_format' => 'jwt',
            ]
        );

        $this->finalizeLogin($tokenResponse);
    }

    public function setAccessToken(string $accessToken): void
    {
        $this->httpClient->setHeader('Authorization', 'Bearer ' . $accessToken);
        $this->accessToken = new OAuthToken($accessToken, 0);
    }

    public function getAccessToken(): ?OAuthToken
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): ?OAuthToken
    {
        return $this->refreshToken;
    }

    public function users(): UsersApi
    {
        return new UsersApi($this->httpClient);
    }

    public function trophies(
        string $npCommunicationId,
        string $serviceName = 'trophy',
        ?string $accountId = null
    ): TrophyTitle {
        return new TrophyTitle($this->httpClient, $npCommunicationId, $serviceName, $accountId);
    }

    private function finalizeLogin(HttpResponse $response): void
    {
        $payload = $response->getJson();
        if (!is_object($payload)) {
            throw new \RuntimeException('Unexpected authentication response payload.');
        }

        if (!isset($payload->access_token, $payload->expires_in)) {
            throw new \RuntimeException('Missing access token in authentication response.');
        }

        $this->accessToken = new OAuthToken((string) $payload->access_token, (int) $payload->expires_in);
        $this->httpClient->setHeader('Authorization', 'Bearer ' . $this->accessToken->getToken());

        if (isset($payload->refresh_token, $payload->refresh_token_expires_in)) {
            $this->refreshToken = new OAuthToken((string) $payload->refresh_token, (int) $payload->refresh_token_expires_in);
        }
    }
}
