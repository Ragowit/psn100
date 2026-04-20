<?php

declare(strict_types=1);

require_once __DIR__ . '/Contracts/PlayStationApiClientInterface.php';
require_once __DIR__ . '/Http/PlayStationHttpTransport.php';
require_once __DIR__ . '/Http/PlayStationAccountLookupUser.php';

final class NativePlayStationApiClient implements PlayStationApiClientInterface
{
    private const OAUTH_AUTHORIZE_URL = 'https://ca.account.sony.com/api/authz/v3/oauth/authorize';
    private const OAUTH_TOKEN_URL = 'https://ca.account.sony.com/api/authz/v3/oauth/token';
    private const OAUTH_CLIENT_ID = '09515159-7237-4370-9b40-3806e67c0891';
    private const OAUTH_CLIENT_SECRET = 'ucPjka5tntB2KqsP';
    private const OAUTH_SCOPE = 'psn:mobile.v2.core psn:clientapp';
    private const OAUTH_REDIRECT_URI = 'com.scee.psxandroid.scecompcall://redirect';

    private readonly PlayStationHttpTransport $transport;

    private ?string $accessToken = null;
    private ?string $refreshToken = null;

    public function __construct()
    {
        $this->transport = new PlayStationHttpTransport(
            requestExecutor: fn (string $path, array $query, array $headers): mixed => $this->requestJson('GET', $path, $query, $headers),
            accountLookupExecutor: fn (string $accountId): object => $this->executeAccountLookup($accountId),
            userSearchExecutor: fn (string $onlineId): iterable => $this->executeUserSearch($onlineId),
            maxAttempts: 2
        );
    }

    public function loginWithNpsso(string $npsso): void
    {
        $authorizationCode = $this->exchangeNpssoForAuthorizationCode($npsso);
        $tokenPayload = $this->exchangeAuthorizationCodeForToken($authorizationCode);
        $this->hydrateTokenState($tokenPayload);
    }

    public function acquireAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function refreshAccessToken(): void
    {
        if (!is_string($this->refreshToken) || $this->refreshToken === '') {
            throw new RuntimeException('No refresh token available.');
        }

        $payload = $this->requestJson(
            'POST',
            self::OAUTH_TOKEN_URL,
            [],
            [
                'authorization' => 'Basic ' . base64_encode(self::OAUTH_CLIENT_ID . ':' . self::OAUTH_CLIENT_SECRET),
                'content-type' => 'application/x-www-form-urlencoded',
            ],
            http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refreshToken,
                'scope' => self::OAUTH_SCOPE,
            ])
        );

        $this->hydrateTokenState($payload);
    }

    public function lookupProfileByOnlineId(string $onlineId): mixed
    {
        try {
            return $this->transport->lookupUserProfile($onlineId);
        } catch (Throwable $exception) {
            if ($this->determineThrowableStatusCode($exception) !== 404) {
                throw $exception;
            }
        }

        $searchCandidate = $this->findSearchCandidateByOnlineId($onlineId);
        if ($searchCandidate === null) {
            throw new RuntimeException(
                sprintf('Unable to resolve accountId for online ID "%s".', $onlineId),
                404
            );
        }

        return $this->transport->lookupUserProfile($searchCandidate['accountId']);
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

    private function executeAccountLookup(string $accountId): PlayStationAccountLookupUser
    {
        $payload = $this->requestJson(
            'GET',
            sprintf(
                'https://m.np.playstation.com/api/userProfile/v1/internal/users/%s/profiles',
                rawurlencode($accountId)
            ),
            [],
            []
        );

        $trophySummaryPayload = $this->requestTrophySummaryForAccountId($accountId);
        if (is_array($trophySummaryPayload)) {
            $payload = $this->mergeAccountLookupPayloadWithTrophySummary($payload, $trophySummaryPayload);
        }

        return PlayStationAccountLookupUser::fromPayload(
            $payload,
            $accountId
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestTrophySummaryForAccountId(string $accountId): ?array
    {
        try {
            return $this->requestJson(
                'GET',
                sprintf(
                    'https://m.np.playstation.com/api/trophy/v1/users/%s/trophySummary',
                    rawurlencode($accountId)
                ),
                [],
                []
            );
        } catch (Throwable $exception) {
            if (!$this->isRecoverableTrophySummaryStatusCode($this->determineThrowableStatusCode($exception))) {
                throw $exception;
            }

            return null;
        }
    }


    private function isRecoverableTrophySummaryStatusCode(?int $statusCode): bool
    {
        if ($statusCode === null) {
            return false;
        }

        if (in_array($statusCode, [401, 403, 404, 429], true)) {
            return true;
        }

        return $statusCode >= 500 && $statusCode <= 599;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $trophySummaryPayload
     * @return array<string, mixed>
     */
    private function mergeAccountLookupPayloadWithTrophySummary(array $payload, array $trophySummaryPayload): array
    {
        if (isset($payload['profile']) && is_array($payload['profile'])) {
            $payload['profile']['trophySummary'] = $trophySummaryPayload;

            return $payload;
        }

        $payload['trophySummary'] = $trophySummaryPayload;

        return $payload;
    }

    /**
     * @return iterable<array<string, string|null>>
     */
    private function executeUserSearch(string $onlineId): iterable
    {
        return $this->mapUserSearchResults(
            $this->requestUserSearchPayload($onlineId),
            $onlineId
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requestUserSearchPayload(string $onlineId): array
    {
        $normalizedOnlineId = trim($onlineId);
        if ($normalizedOnlineId === '') {
            return [];
        }

        return $this->requestJson(
            'POST',
            'https://m.np.playstation.com/api/search/v1/universalSearch',
            [],
            ['content-type' => 'application/json'],
            json_encode([
                'age' => '69',
                'countryCode' => 'us',
                'domainRequests' => [[
                    'domain' => 'SocialAllAccounts',
                    'pagination' => [
                        'cursor' => '',
                        'pageSize' => '50',
                    ],
                ]],
                'languageCode' => 'en',
                'searchTerm' => $normalizedOnlineId,
            ], JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return iterable<array{onlineId: string, country: string|null, aboutMe: string|null}>
     */
    private function mapUserSearchResults(array $payload, ?string $queriedOnlineId = null): iterable
    {
        $normalizedQueriedOnlineId = is_string($queriedOnlineId) ? trim($queriedOnlineId) : '';

        foreach ($this->extractUserSearchCandidates($payload) as $candidate) {
            $preferredOnlineId = $candidate['currentOnlineId'] ?? $candidate['onlineId'];

            if ($normalizedQueriedOnlineId !== ''
                && strcasecmp($candidate['onlineId'], $normalizedQueriedOnlineId) === 0) {
                $preferredOnlineId = $candidate['onlineId'];
            } elseif ($normalizedQueriedOnlineId !== ''
                && is_string($candidate['currentOnlineId'])
                && strcasecmp($candidate['currentOnlineId'], $normalizedQueriedOnlineId) === 0) {
                $preferredOnlineId = $candidate['currentOnlineId'];
            }

            yield [
                'onlineId' => $preferredOnlineId,
                'country' => $candidate['country'],
                'aboutMe' => $candidate['aboutMe'],
            ];
        }
    }

    /**
     * @return array{accountId: string, onlineId: string, currentOnlineId: string|null, country: string|null, aboutMe: string|null}|null
     */
    private function findSearchCandidateByOnlineId(string $onlineId): ?array
    {
        $normalizedOnlineId = trim($onlineId);
        if ($normalizedOnlineId === '') {
            return null;
        }

        $payload = $this->requestUserSearchPayload($normalizedOnlineId);

        foreach ($this->extractUserSearchCandidates($payload) as $candidate) {
            $matchesOnlineId = strcasecmp($candidate['onlineId'], $normalizedOnlineId) === 0;
            $matchesCurrentOnlineId = is_string($candidate['currentOnlineId'])
                && strcasecmp($candidate['currentOnlineId'], $normalizedOnlineId) === 0;

            if (!$matchesOnlineId && !$matchesCurrentOnlineId) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array{accountId: string, onlineId: string, currentOnlineId: string|null, country: string|null, aboutMe: string|null}>
     */
    private function extractUserSearchCandidates(array $payload): array
    {
        $results = [];
        $nodes = [$payload];

        while ($nodes !== []) {
            $node = array_pop($nodes);
            if (!is_array($node)) {
                continue;
            }

            $accountId = $this->normalizeAccountId($node['accountId'] ?? null);
            $onlineId = $node['onlineId'] ?? null;
            $currentOnlineId = $node['currentOnlineId'] ?? null;

            $normalizedOnlineId = is_string($onlineId) && trim($onlineId) !== '' ? trim($onlineId) : null;
            $normalizedCurrentOnlineId = is_string($currentOnlineId) && trim($currentOnlineId) !== ''
                ? trim($currentOnlineId)
                : null;

            if ($accountId !== null && ($normalizedOnlineId !== null || $normalizedCurrentOnlineId !== null)) {
                $results[] = [
                    'accountId' => $accountId,
                    'onlineId' => $normalizedOnlineId ?? $normalizedCurrentOnlineId,
                    'currentOnlineId' => $normalizedCurrentOnlineId,
                    'country' => isset($node['country']) && is_string($node['country']) ? $node['country'] : null,
                    'aboutMe' => isset($node['aboutMe']) && is_string($node['aboutMe']) ? $node['aboutMe'] : null,
                ];
            }

            $nestedValues = [];
            foreach ($node as $value) {
                if (is_array($value)) {
                    $nestedValues[] = $value;
                }
            }

            for ($index = count($nestedValues) - 1; $index >= 0; $index--) {
                $nodes[] = $nestedValues[$index];
            }
        }

        return $results;
    }

    private function normalizeAccountId(mixed $accountId): ?string
    {
        if (is_string($accountId)) {
            $normalizedAccountId = trim($accountId);

            return $normalizedAccountId !== '' ? $normalizedAccountId : null;
        }

        if (is_int($accountId)) {
            return (string) $accountId;
        }

        if (is_float($accountId) && is_finite($accountId) && floor($accountId) === $accountId) {
            return sprintf('%.0F', $accountId);
        }

        return null;
    }

    private function determineThrowableStatusCode(Throwable $exception): ?int
    {
        $statusCode = $exception->getCode();
        if (is_int($statusCode) && $statusCode > 0) {
            return $statusCode;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof Throwable) {
            return $this->determineThrowableStatusCode($previous);
        }

        return null;
    }

    private function exchangeNpssoForAuthorizationCode(string $npsso): string
    {
        $response = $this->rawHttpRequest(
            'GET',
            self::OAUTH_AUTHORIZE_URL . '?' . http_build_query([
                'access_type' => 'offline',
                'client_id' => self::OAUTH_CLIENT_ID,
                'redirect_uri' => self::OAUTH_REDIRECT_URI,
                'response_type' => 'code',
                'scope' => self::OAUTH_SCOPE,
            ]),
            ['cookie' => sprintf('npsso=%s', $npsso)],
            null,
            false
        );

        $location = $response['headers']['location'] ?? '';
        if (!is_string($location) || $location === '') {
            throw new RuntimeException('Unable to exchange NPSSO for authorization code.');
        }

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $code = $query['code'] ?? null;
        if (!is_string($code) || $code === '') {
            throw new RuntimeException('Authorization code was not present in PlayStation response.');
        }

        return $code;
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeAuthorizationCodeForToken(string $authorizationCode): array
    {
        return $this->requestJson(
            'POST',
            self::OAUTH_TOKEN_URL,
            [],
            [
                'authorization' => 'Basic ' . base64_encode(self::OAUTH_CLIENT_ID . ':' . self::OAUTH_CLIENT_SECRET),
                'content-type' => 'application/x-www-form-urlencoded',
            ],
            http_build_query([
                'code' => $authorizationCode,
                'grant_type' => 'authorization_code',
                'redirect_uri' => self::OAUTH_REDIRECT_URI,
                'scope' => self::OAUTH_SCOPE,
                'token_format' => 'jwt',
            ])
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateTokenState(array $payload): void
    {
        $accessToken = $payload['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('PlayStation token response did not include an access token.');
        }

        $refreshToken = $payload['refresh_token'] ?? null;
        $this->accessToken = $accessToken;
        $this->refreshToken = is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null;
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function requestJson(
        string $method,
        string $url,
        array $query = [],
        array $headers = [],
        ?string $body = null
    ): array {
        $resolvedUrl = $query === [] ? $url : $url . '?' . http_build_query($query);

        if (!isset($headers['authorization']) && is_string($this->accessToken) && $this->accessToken !== '') {
            $headers['authorization'] = 'Bearer ' . $this->accessToken;
        }

        $response = $this->rawHttpRequest($method, $resolvedUrl, $headers, $body, true);
        $payload = json_decode($response['body'], true);

        if (!is_array($payload)) {
            throw new RuntimeException('Invalid JSON response from PlayStation API.');
        }

        return $payload;
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function rawHttpRequest(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        bool $followLocation
    ): array {
        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        $responseHeaders = [];
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_FOLLOWLOCATION => $followLocation,
            CURLOPT_HTTPHEADER => array_map(
                static fn (string $name, string $value): string => $name . ': ' . $value,
                array_keys($headers),
                $headers
            ),
            CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($headerLine);
            },
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $rawBody = curl_exec($curl);
        if (!is_string($rawBody)) {
            $error = curl_error($curl);
            throw new RuntimeException($error === '' ? 'Unknown cURL failure.' : $error);
        }

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if ($status >= 400) {
            throw new RuntimeException(sprintf('PlayStation API request failed with HTTP %d.', $status), $status);
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $rawBody,
        ];
    }
}
