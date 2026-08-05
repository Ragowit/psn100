<?php

declare(strict_types=1);

require_once __DIR__ . '/AdminLoginThrottleService.php';

final readonly class AdminAuthService
{
    private const string SESSION_AUTHENTICATED_KEY = 'admin_authenticated';

    private const string SESSION_USERNAME_KEY = 'admin_username';

    public function __construct(
        final private AdminUserRepository $adminUserRepository,
        final private ?AdminLoginThrottleService $loginThrottle = null,
    ) {
    }

    #[\NoDiscard('Check whether admin authentication is configured before allowing login.')]
    public function isConfigured(): bool
    {
        return $this->adminUserRepository->hasAnyAdmin();
    }

    #[\NoDiscard('Check authentication state before granting access to admin pages.')]
    public function isAuthenticated(): bool
    {
        return ($_SESSION[self::SESSION_AUTHENTICATED_KEY] ?? false) === true;
    }

    public function getAuthenticatedUsername(): ?string
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $username = $_SESSION[self::SESSION_USERNAME_KEY] ?? null;

        return is_string($username) && $username !== '' ? $username : null;
    }

    #[\NoDiscard('Check whether the client IP is locked out before attempting login.')]
    public function isLoginLocked(string $ipAddress): bool
    {
        return $this->loginThrottle?->isLocked($ipAddress) ?? false;
    }

    public function getLoginLockoutRemainingSeconds(string $ipAddress): int
    {
        return $this->loginThrottle?->getLockoutRemainingSeconds($ipAddress) ?? 0;
    }

    #[\NoDiscard('The login result must be checked before treating the session as authenticated.')]
    public function login(string $username, #[\SensitiveParameter] string $password, string $ipAddress = ''): bool
    {
        if ($ipAddress !== '' && $this->isLoginLocked($ipAddress)) {
            return false;
        }

        if (!$this->adminUserRepository->verifyCredentials($username, $password)) {
            if ($ipAddress !== '') {
                $this->loginThrottle?->recordFailure($ipAddress);
            }

            return false;
        }

        if ($ipAddress !== '') {
            $this->loginThrottle?->recordSuccess($ipAddress);
        }

        $_SESSION[self::SESSION_AUTHENTICATED_KEY] = true;
        $_SESSION[self::SESSION_USERNAME_KEY] = $username;
        session_regenerate_id(true);

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_AUTHENTICATED_KEY], $_SESSION[self::SESSION_USERNAME_KEY]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
