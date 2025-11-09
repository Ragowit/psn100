<?php

declare(strict_types=1);

namespace Achievements\PsnApi;

use Achievements\PsnApi\Exceptions\ApiException;
use Achievements\PsnApi\Exceptions\AuthenticationException;

final class Authenticator
{
    private const AUTH_BASE_URL = 'https://ca.account.sony.com/api/authz/v3/oauth';

    private HttpClient $httpClient;

    public function __construct(?HttpClient $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new HttpClient();
    }

    public function exchangeNpssoForAccessCode(string $npsso): string
    {
        if ($npsso === '') {
            throw new AuthenticationException('Empty NPSSO value provided.');
        }

        $query = http_build_query([
            'access_type' => 'offline',
            'client_id' => '09515159-7237-4370-9b40-3806e67c0891',
            'redirect_uri' => 'com.scee.psxandroid.scecompcall://redirect',
            'response_type' => 'code',
            'scope' => 'psn:mobile.v2.core psn:clientapp',
        ]);

        $headers = $this->httpClient->requestHeaders(
            'GET',
            self::AUTH_BASE_URL . '/authorize?' . $query,
            ['Cookie' => 'npsso=' . $npsso]
        );

        if (!isset($headers['location'])) {
            throw new AuthenticationException('Unable to retrieve access code from NPSSO token.');
        }

        $location = $headers['location'];
        $parsedUrl = parse_url($location);

        if ($parsedUrl === false) {
            throw new AuthenticationException('Unexpected authorization response.');
        }

        $query = $parsedUrl['query'] ?? null;

        if ($query === null && isset($parsedUrl['fragment'])) {
            $query = $parsedUrl['fragment'];
        }

        if ($query === null) {
            throw new AuthenticationException('Authorization response did not include an access code.');
        }

        parse_str($query, $params);

        /** @var array<string, string> $params */
        if (!isset($params['code']) || $params['code'] === '') {
            throw new AuthenticationException('Authorization response did not include an access code.');
        }

        return $params['code'];
    }

    public function exchangeAccessCodeForAuthTokens(string $code): AuthTokens
    {
        $payload = http_build_query([
            'code' => $code,
            'redirect_uri' => 'com.scee.psxandroid.scecompcall://redirect',
            'grant_type' => 'authorization_code',
            'token_format' => 'jwt',
        ]);

        $response = $this->httpClient->request(
            'POST',
            self::AUTH_BASE_URL . '/token',
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic MDk1MTUxNTktNzIzNy00MzcwLTliNDAtMzgwNmU2N2MwODkxOnVjUGprYTV0bnRCMktxc1A=',
            ],
            $payload
        );

        return $this->createAuthTokensFromResponse($response);
    }

    public function exchangeRefreshTokenForAuthTokens(string $refreshToken): AuthTokens
    {
        $payload = http_build_query([
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'token_format' => 'jwt',
            'scope' => 'psn:mobile.v2.core psn:clientapp',
        ]);

        $response = $this->httpClient->request(
            'POST',
            self::AUTH_BASE_URL . '/token',
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic MDk1MTUxNTktNzIzNy00MzcwLTliNDAtMzgwNmU2N2MwODkxOnVjUGprYTV0bnRCMktxc1A=',
            ],
            $payload
        );

        return $this->createAuthTokensFromResponse($response);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function createAuthTokensFromResponse(array $response): AuthTokens
    {
        if (!isset($response['access_token'], $response['refresh_token'], $response['expires_in'], $response['refresh_token_expires_in'])) {
            throw new ApiException('Unexpected authentication response.', 0, $response);
        }

        return new AuthTokens(
            (string) $response['access_token'],
            (string) $response['refresh_token'],
            (int) $response['expires_in'],
            (int) $response['refresh_token_expires_in']
        );
    }
}
