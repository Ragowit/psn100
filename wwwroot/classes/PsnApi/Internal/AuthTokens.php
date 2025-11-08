<?php

declare(strict_types=1);

namespace PsnApi\Internal;

final class AuthTokens
{
    private string $accessToken;

    private string $refreshToken;

    private int $expiresAt;

    public function __construct(string $accessToken, string $refreshToken, int $expiresAt)
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->expiresAt = $expiresAt;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== 0 && $this->expiresAt <= time();
    }
}
