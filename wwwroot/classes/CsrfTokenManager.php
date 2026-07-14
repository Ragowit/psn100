<?php

declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';

final class CsrfTokenManager
{
    private const string SESSION_KEY_PREFIX = 'csrf_token_';

    public static function getToken(string $scope): string
    {
        SessionManager::ensureStarted();

        $sessionKey = self::sessionKey($scope);
        $token = $_SESSION[$sessionKey] ?? null;

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[$sessionKey] = $token;
        }

        return $token;
    }

    #[\NoDiscard]
    public static function validate(string $scope, mixed $submittedToken): bool
    {
        SessionManager::ensureStarted();

        if (!is_string($submittedToken) || $submittedToken === '') {
            return false;
        }

        $expectedToken = $_SESSION[self::sessionKey($scope)] ?? null;

        if (!is_string($expectedToken) || $expectedToken === '') {
            return false;
        }

        return hash_equals($expectedToken, $submittedToken);
    }

    public static function hiddenField(string $scope): string
    {
        $token = htmlspecialchars(self::getToken($scope), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }

    private static function sessionKey(string $scope): string
    {
        return self::SESSION_KEY_PREFIX . $scope;
    }
}
