<?php

declare(strict_types=1);

final class AdminAuthService
{
    private const string SESSION_AUTHENTICATED_KEY = 'admin_authenticated';

    public function __construct(private readonly AdminAuthConfig $config)
    {
    }

    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    public function isAuthenticated(): bool
    {
        return ($_SESSION[self::SESSION_AUTHENTICATED_KEY] ?? false) === true;
    }

    public function login(string $username, string $password): bool
    {
        if (!$this->config->isConfigured()) {
            return false;
        }

        if (!hash_equals($this->config->getUsername(), $username)) {
            return false;
        }

        if (!$this->config->verifyPassword($password)) {
            return false;
        }

        $_SESSION[self::SESSION_AUTHENTICATED_KEY] = true;
        session_regenerate_id(true);

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_AUTHENTICATED_KEY]);
    }
}
