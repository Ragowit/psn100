<?php

declare(strict_types=1);

require_once __DIR__ . '/../CsrfTokenManager.php';
require_once __DIR__ . '/../SessionManager.php';
require_once __DIR__ . '/../Html.php';
require_once __DIR__ . '/AdminAuthService.php';
require_once __DIR__ . '/AdminLoginThrottleService.php';
require_once __DIR__ . '/AdminUserRepository.php';

final class AdminBootstrap
{
    public static function requireAuthenticatedAdminPage(): void
    {
        SessionManager::ensureStarted();

        $authService = self::createAuthService();

        if (!$authService->isConfigured()) {
            self::respondNotConfigured();
        }

        if (!$authService->isAuthenticated()) {
            self::respondLoginRedirect();
        }

        if (self::isPostRequest()) {
            self::validateCsrfToken();
        }
    }

    public static function createAuthService(): AdminAuthService
    {
        $database = self::requireDatabase();

        return new AdminAuthService(
            new AdminUserRepository($database),
            new AdminLoginThrottleService($database),
        );
    }

    public static function getCsrfToken(): string
    {
        return CsrfTokenManager::getToken('admin');
    }

    public static function renderCsrfField(): void
    {
        echo CsrfTokenManager::hiddenField('admin');
    }

    public static function renderCsrfMetaTag(): void
    {
        $token = Html::escape(self::getCsrfToken());
        echo '<meta name="csrf-token" content="' . $token . '">';
    }

    private static function requireDatabase(): PDO
    {
        global $database;

        if (!isset($database) || !$database instanceof PDO) {
            throw new LogicException('Database connection is required for admin authentication.');
        }

        return $database;
    }

    private static function isPostRequest(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        return is_string($method) && strtoupper($method) === 'POST';
    }

    public static function requireValidPostCsrfToken(): void
    {
        self::validateCsrfToken();
    }

    private static function validateCsrfToken(): void
    {
        $submittedToken = $_POST['_csrf_token'] ?? '';

        if (!CsrfTokenManager::validate('admin', $submittedToken)) {
            self::respondInvalidCsrfToken();
        }
    }

    private static function respondLoginRedirect(): never
    {
        header('Location: /admin/login.php', true, 303);
        exit;
    }

    private static function respondInvalidCsrfToken(): never
    {
        http_response_code(403);
        echo 'Invalid CSRF token.';
        exit;
    }

    private static function respondNotConfigured(): never
    {
        http_response_code(503);
        echo 'Admin access is not configured. Add at least one row to the admin_user table.';
        exit;
    }
}
