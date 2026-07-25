<?php

declare(strict_types=1);

final readonly class PlayerUrlBuilder
{
    #[\NoDiscard]
    public static function playerPath(string $onlineId): string
    {
        return self::buildPath('player', rawurlencode($onlineId));
    }

    #[\NoDiscard]
    public static function playerReportPath(string $onlineId): string
    {
        return self::buildPath('player', rawurlencode($onlineId), 'report');
    }

    #[\NoDiscard]
    public static function gamePlayerPath(string $gameSlug, string $onlineId): string
    {
        return self::buildPath('game', $gameSlug, rawurlencode($onlineId));
    }

    #[\NoDiscard]
    public static function gamePath(string $gameSlug, ?string $onlineId = null): string
    {
        if ($onlineId === null || $onlineId === '') {
            return self::buildPath('game', $gameSlug);
        }

        return self::gamePlayerPath($gameSlug, $onlineId);
    }

    private static function buildPath(string ...$segments): string
    {
        $path = '/' . implode('/', $segments);

        return Uri\Rfc3986\Uri::parse($path)?->toRawString() ?? $path;
    }
}
