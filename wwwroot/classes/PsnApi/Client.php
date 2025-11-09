<?php

declare(strict_types=1);

namespace Achievements\PsnApi;

use Achievements\PsnApi\Exceptions\ApiException;
use Achievements\PsnApi\Exceptions\AuthenticationException;
use Achievements\PsnApi\Trophies\TitleTrophySet;
use Achievements\PsnApi\Users\UsersService;

final class Client
{
    private HttpClient $httpClient;

    private Authenticator $authenticator;

    private ?AuthTokens $tokens = null;

    /** @var array<string, TitleTrophySet> */
    private array $trophyCache = [];

    private ?UsersService $usersService = null;

    public function __construct(?HttpClient $httpClient = null, ?Authenticator $authenticator = null)
    {
        $this->httpClient = $httpClient ?? new HttpClient();
        $this->authenticator = $authenticator ?? new Authenticator($this->httpClient);
    }

    public function loginWithNpsso(string $npsso): void
    {
        $code = $this->authenticator->exchangeNpssoForAccessCode($npsso);
        $this->tokens = $this->authenticator->exchangeAccessCodeForAuthTokens($code);
    }

    public function users(): UsersService
    {
        if ($this->usersService === null) {
            $this->usersService = new UsersService($this);
        }

        return $this->usersService;
    }

    public function trophies(string $npCommunicationId, string $serviceName): TitleTrophySet
    {
        $cacheKey = $npCommunicationId . '|' . $serviceName;

        if (!isset($this->trophyCache[$cacheKey])) {
            $this->trophyCache[$cacheKey] = new TitleTrophySet($this, $npCommunicationId, $serviceName);
        }

        return $this->trophyCache[$cacheKey];
    }

    /**
     * @return array<string, string>
     */
    public function authorizationHeaders(): array
    {
        $accessToken = $this->getAccessToken();

        return [
            'Authorization' => 'Bearer ' . $accessToken,
        ];
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function requestJson(string $method, string $url, ?array $body = null, array $headers = [], bool $encodeBody = false): array
    {
        $requestHeaders = $headers;
        $requestHeaders['Authorization'] = 'Bearer ' . $this->getAccessToken();

        $payload = null;

        if ($body !== null) {
            if ($encodeBody) {
                try {
                    $payload = json_encode($body, JSON_THROW_ON_ERROR);
                } catch (\JsonException $exception) {
                    throw new ApiException('Unable to encode request payload as JSON.', 0, null, $exception);
                }

                $requestHeaders['Content-Type'] = 'application/json';
            } else {
                $payload = http_build_query($body);

                if (!isset($requestHeaders['Content-Type'])) {
                    $requestHeaders['Content-Type'] = 'application/x-www-form-urlencoded';
                }
            }
        }

        return $this->httpClient->request($method, $url, $requestHeaders, $payload);
    }

    private function getAccessToken(): string
    {
        if ($this->tokens === null) {
            throw new AuthenticationException('The PlayStation client is not authenticated.');
        }

        if ($this->tokens->willAccessTokenExpireWithin(120)) {
            if ($this->tokens->isRefreshTokenExpired()) {
                throw new AuthenticationException('Unable to refresh authentication token.');
            }

            $newTokens = $this->authenticator->exchangeRefreshTokenForAuthTokens($this->tokens->refreshToken());
            $this->tokens->updateFrom($newTokens);
        }

        return $this->tokens->accessToken();
    }
}
