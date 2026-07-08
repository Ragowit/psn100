<?php

declare(strict_types=1);

/**
 * Ensures cron entry scripts cannot be executed over HTTP.
 *
 * Apache should also deny web access (see wwwroot/cron/.htaccess.example). This guard
 * covers the PHP built-in server, misconfigured virtual hosts, and other SAPIs.
 */
final class CronCliAccessGuard
{
    public static function requireCliExecution(): void
    {
        if (self::isCli()) {
            return;
        }

        self::respondForbidden();
    }

    /**
     * @param array<string, mixed> $serverParameters
     */
    public static function denyWebCronScriptAccess(array $serverParameters = []): void
    {
        if (self::isCli()) {
            return;
        }

        if (!self::isCronScriptRequest($serverParameters)) {
            return;
        }

        self::respondForbidden();
    }

    /**
     * @param array<string, mixed> $serverParameters
     */
    public static function isCronScriptRequest(array $serverParameters): bool
    {
        $scriptName = $serverParameters['SCRIPT_NAME'] ?? $serverParameters['PHP_SELF'] ?? '';

        if (!is_string($scriptName) || $scriptName === '') {
            return false;
        }

        return str_contains($scriptName, '/cron/');
    }

    private static function isCli(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }

    private static function respondForbidden(): never
    {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
        }

        echo 'Forbidden';
        exit;
    }
}
