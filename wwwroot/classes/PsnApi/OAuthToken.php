<?php

declare(strict_types=1);

namespace PsnApi;

final class OAuthToken
{
    private string $token;

    private int $expiresIn;

    private int $createdAt;

    public function __construct(string $token, int $expiresIn)
    {
        $this->token = $token;
        $this->expiresIn = $expiresIn;
        $this->createdAt = time();
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function isExpired(): bool
    {
        return ($this->createdAt + $this->expiresIn) <= time();
    }
}
