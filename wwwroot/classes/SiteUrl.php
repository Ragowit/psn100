<?php

declare(strict_types=1);

final class SiteUrl
{
    private const string ORIGIN = 'https://psn100.net';

    #[\NoDiscard]
    public static function absolute(string $path): string
    {
        $normalizedPath = self::normalizePath($path);

        return Uri\Rfc3986\Uri::parse(self::ORIGIN)
            ?->withPath($normalizedPath)
            ->toRawString()
            ?? self::ORIGIN . $normalizedPath;
    }

    private static function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }
}
