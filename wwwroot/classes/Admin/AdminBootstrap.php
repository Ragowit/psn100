<?php

declare(strict_types=1);

require_once __DIR__ . '/../CsrfTokenManager.php';
require_once __DIR__ . '/../SessionManager.php';
require_once __DIR__ . '/AdminAuthConfig.php';
require_once __DIR__ . '/AdminAuthService.php';

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
            header('Location: /admin/login.php', true, 303);
            exit;
        }

        if (self::isPostRequest()) {
            self::validateCsrfToken();
        }
    }

    public static function createAuthService(): AdminAuthService
    {
        return new AdminAuthService(AdminAuthConfig::fromEnvironment());
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
        $token = htmlspecialchars(self::getCsrfToken(), ENT_QUOTES, 'UTF-8');
        echo '<meta name="csrf-token" content="' . $token . '">';
    }

    private static function isPostRequest(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        return is_string($method) && strtoupper($method) === 'POST';
    }

    private static function validateCsrfToken(): void
    {
        $submittedToken = $_POST['_csrf_token'] ?? '';

        if (!CsrfTokenManager::validate('admin', $submittedToken)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            exit;
        }
    }

    private static function respondNotConfigured(): never
    {
        http_response_code(503);
        echo 'Admin access is not configured.';
        exit;
    }
}
