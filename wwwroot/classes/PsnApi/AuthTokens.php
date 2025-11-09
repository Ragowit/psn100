<?php

declare(strict_types=1);

namespace Achievements\PsnApi;

final class AuthTokens
{
    private string $accessToken;

    private string $refreshToken;

    private int $accessTokenExpiresAt;

    private int $refreshTokenExpiresAt;

    public function __construct(string $accessToken, string $refreshToken, int $accessTokenExpiresIn, int $refreshTokenExpiresIn)
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $currentTime = time();
        $this->accessTokenExpiresAt = $currentTime + $accessTokenExpiresIn;
        $this->refreshTokenExpiresAt = $currentTime + $refreshTokenExpiresIn;
    }

    public function accessToken(): string
    {
        return $this->accessToken;
    }

    public function refreshToken(): string
    {
        return $this->refreshToken;
    }

    public function updateFrom(self $tokens): void
    {
        $this->accessToken = $tokens->accessToken;
        $this->refreshToken = $tokens->refreshToken;
        $this->accessTokenExpiresAt = $tokens->accessTokenExpiresAt;
        $this->refreshTokenExpiresAt = $tokens->refreshTokenExpiresAt;
    }

    public function willAccessTokenExpireWithin(int $seconds): bool
    {
        return $this->accessTokenExpiresAt - time() <= $seconds;
    }

    public function isRefreshTokenExpired(): bool
    {
        return $this->refreshTokenExpiresAt <= time();
    }
}
