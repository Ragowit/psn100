<?php

declare(strict_types=1);

namespace PsnApi;

use PsnApi\Exception\AuthenticationException;
use PsnApi\Exception\NotFoundException;
use PsnApi\Exception\PsnApiException;
use PsnApi\Internal\AuthTokens;

final class Client
{
    private const AUTH_BASE_URI = 'https://ca.account.sony.com';
    private const MOBILE_BASE_URI = 'https://m.np.playstation.com';
    private const USER_AGENT = 'PlayStation/21090100 CFNetwork/1126 Darwin/19.5.0';
    private const ACCEPT_LANGUAGE = 'en-US';
    private const AUTHORIZATION_SCOPE = 'psn:mobile.v2.core psn:clientapp';
    private const AUTH_SMCID = 'psapp:settings-entrance';
    private const AUTH_CLIENT_ID = '09515159-7237-4370-9b40-3806e67c0891';
    private const AUTH_REDIRECT_URI = 'com.scee.psxandroid.scecompcall://redirect';
    private const AUTHORIZATION_HEADER = 'Basic MDk1MTUxNTktNzIzNy00MzcwLTliNDAtMzgwNmU2N2MwODkxOnVjUGprYTV0bnRCMktxc1A=';

    private ?AuthTokens $authTokens = null;

    private ?Users $users = null;

    public function loginWithNpsso(string $npsso): void
    {
        $trimmed = trim($npsso);
        if ($trimmed === '') {
            throw new AuthenticationException('NPSSO token must not be empty.');
        }

        $code = $this->fetchAuthorizationCode($trimmed);
        $this->authTokens = $this->exchangeAuthorizationCodeForTokens($code, $trimmed);
    }

    public function users(): Users
    {
        if ($this->users === null) {
            $this->users = new Users($this);
        }

        return $this->users;
    }

    public function trophies(string $npCommunicationId, string $serviceName): TitleTrophySet
    {
        $this->assertAuthenticated();

        return new TitleTrophySet($this, $npCommunicationId, $serviceName);
    }

    /**
     * @param array<string, scalar>|string $queryParameters
     * @return array<string, mixed>
     */
    public function get(string $path, array|string $queryParameters = []): array
    {
        return $this->request(
            $path,
            'GET',
            $queryParameters,
            [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->authTokens->getAccessToken(),
                'User-Agent: ' . self::USER_AGENT,
                'Accept-Language: ' . self::ACCEPT_LANGUAGE,
            ]
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, scalar>|string $queryParameters
     * @param list<string> $additionalHeaders
     * @return array<string, mixed>
     */
    public function postJson(string $path, array $payload, array|string $queryParameters = [], array $additionalHeaders = []): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new PsnApiException('Unable to encode JSON payload.');
        }

        return $this->request(
            $path,
            'POST',
            $queryParameters,
            [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->authTokens->getAccessToken(),
                'Content-Type: application/json',
                'User-Agent: ' . self::USER_AGENT,
                'Accept-Language: ' . self::ACCEPT_LANGUAGE,
                ...$additionalHeaders,
            ],
            $body
        );
    }

    /**
     * @return array{status:int, headers:list<string>, body:string}
     */
    private function sendCurlRequest(string $uri, array $headers, string $method = 'GET', ?string $body = null): array
    {
        $handle = curl_init($uri);
        if ($handle === false) {
            throw new PsnApiException('Unable to initialize HTTP request.');
        }

        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HEADER, true);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $rawResponse = curl_exec($handle);
        if ($rawResponse === false) {
            $errorMessage = curl_error($handle);
            curl_close($handle);

            throw new PsnApiException('HTTP request failed: ' . ($errorMessage !== '' ? $errorMessage : 'Unknown error'));
        }

        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        curl_close($handle);

        $headerSection = substr($rawResponse, 0, $headerSize) ?: '';
        $bodySection = substr($rawResponse, $headerSize) ?: '';

        $headersList = [];
        foreach (preg_split('/\r?\n/', trim($headerSection)) as $headerLine) {
            if ($headerLine === '') {
                continue;
            }

            $headersList[] = $headerLine;
        }

        return [
            'status' => $statusCode,
            'headers' => $headersList,
            'body' => $bodySection,
        ];
    }

    /**
     * @param array<string, scalar>|string $queryParameters
     * @param list<string> $headers
     * @return array<string, mixed>
     */
    private function request(string $path, string $method, array|string $queryParameters, array $headers, ?string $body = null): array
    {
        $this->assertAuthenticated();

        $uri = self::MOBILE_BASE_URI . '/' . ltrim($path, '/');
        $queryString = $this->buildQueryString($queryParameters);
        if ($queryString !== '') {
            $uri .= '?' . $queryString;
        }

        $response = $this->sendCurlRequest($uri, $headers, $method, $body);

        if ($response['status'] === 401 || $response['status'] === 403) {
            throw new AuthenticationException(sprintf('PSN API authentication failed with status %d.', $response['status']));
        }

        if ($response['status'] === 404) {
            throw new NotFoundException(sprintf('PSN API resource "%s" was not found.', $path));
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new PsnApiException(sprintf('PSN API request failed with status %d.', $response['status']));
        }

        return $this->decodeJsonResponse($response['body'], $response['status']);
    }

    /**
     * @param array<string, scalar>|string $queryParameters
     */
    private function buildQueryString(array|string $queryParameters): string
    {
        if (is_string($queryParameters)) {
            return ltrim($queryParameters, '?');
        }

        if ($queryParameters === []) {
            return '';
        }

        $pairs = [];
        foreach ($queryParameters as $key => $value) {
            if (!is_scalar($value)) {
                throw new PsnApiException('Query parameters must be scalar values.');
            }

            $pairs[] = rawurlencode((string) $key) . '=' . $this->escapeUriComponent((string) $value);
        }

        return implode('&', $pairs);
    }

    private function escapeUriComponent(string $value): string
    {
        $encoded = rawurlencode($value);

        return str_replace('%7E', '~', $encoded);
    }

    private function fetchAuthorizationCode(string $npsso): string
    {
        $uri = self::AUTH_BASE_URI . '/api/authz/v3/oauth/authorize?' . http_build_query([
            'access_type' => 'offline',
            'client_id' => self::AUTH_CLIENT_ID,
            'response_type' => 'code',
            'scope' => self::AUTHORIZATION_SCOPE,
            'redirect_uri' => self::AUTH_REDIRECT_URI,
            'smcid' => self::AUTH_SMCID,
        ]);

        $response = $this->sendCurlRequest(
            $uri,
            [
                'Cookie: npsso=' . $npsso,
                'User-Agent: psnapi-php/1.0',
            ]
        );

        if ($response['status'] < 300 || $response['status'] >= 400) {
            throw new AuthenticationException('Unexpected response when requesting authorization code.');
        }

        $locationHeader = $this->findHeader($response['headers'], 'Location');
        if ($locationHeader === null) {
            throw new AuthenticationException('Authorization response did not include a redirect.');
        }

        $location = $locationHeader['value'];
        $parsed = parse_url($location);
        if (!is_array($parsed) || !isset($parsed['query'])) {
            throw new AuthenticationException('Unable to parse authorization redirect.');
        }

        parse_str($parsed['query'], $queryParameters);
        $code = (string) ($queryParameters['code'] ?? '');
        if ($code === '') {
            throw new AuthenticationException('Authorization redirect did not contain a code.');
        }

        return $code;
    }

    private function exchangeAuthorizationCodeForTokens(string $code, string $npsso): AuthTokens
    {
        $uri = self::AUTH_BASE_URI . '/api/authz/v3/oauth/token';
        $payload = http_build_query([
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => self::AUTH_REDIRECT_URI,
            'token_format' => 'jwt',
            'scope' => self::AUTHORIZATION_SCOPE,
            'smcid' => self::AUTH_SMCID,
            'access_type' => 'offline',
        ]);

        $response = $this->sendCurlRequest(
            $uri,
            [
                'Authorization: ' . self::AUTHORIZATION_HEADER,
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: psnapi-php/1.0',
                'Cookie: npsso=' . $npsso,
            ],
            'POST',
            $payload
        );

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new AuthenticationException('Failed to exchange authorization code for tokens.');
        }

        $data = $this->decodeJsonResponse($response['body'], $response['status']);

        $accessToken = (string) ($data['access_token'] ?? '');
        $refreshToken = (string) ($data['refresh_token'] ?? '');
        $expiresIn = (int) ($data['expires_in'] ?? 0);

        if ($accessToken === '' || $refreshToken === '') {
            throw new AuthenticationException('Authentication response did not include required tokens.');
        }

        $expiresAt = $expiresIn > 0 ? time() + $expiresIn : 0;

        return new AuthTokens($accessToken, $refreshToken, $expiresAt);
    }

    private function assertAuthenticated(): void
    {
        if ($this->authTokens === null || $this->authTokens->isExpired()) {
            throw new AuthenticationException('Client is not authenticated.');
        }
    }

    /**
     * @param list<string> $headers
     * @return array{key:string,value:string}|null
     */
    private function findHeader(array $headers, string $name): ?array
    {
        $lowerName = strtolower($name);
        foreach ($headers as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) !== 2) {
                continue;
            }

            if (strtolower(trim($parts[0])) === $lowerName) {
                return ['key' => trim($parts[0]), 'value' => trim($parts[1])];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(string $body, int $statusCode): array
    {
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new PsnApiException(sprintf('Unable to decode JSON response (HTTP %d): %s', $statusCode, json_last_error_msg()));
        }

        return $decoded;
    }
}
