<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerRouteView.php';
require_once __DIR__ . '/RouteName.php';

final readonly class PlayerUrlBuilder
{
    #[\NoDiscard]
    public static function playerPath(string $onlineId): string
    {
        return self::buildPath(RouteName::Player->value, rawurlencode($onlineId));
    }

    #[\NoDiscard]
    public static function playerReportPath(string $onlineId): string
    {
        return self::buildPath(
            RouteName::Player->value,
            rawurlencode($onlineId),
            PlayerRouteView::Report->value
        );
    }

    #[\NoDiscard]
    public static function gamePlayerPath(string $gameSlug, string $onlineId): string
    {
        return self::buildPath(RouteName::Game->value, $gameSlug, rawurlencode($onlineId));
    }

    #[\NoDiscard]
    public static function gamePath(string $gameSlug, ?string $onlineId = null): string
    {
        if ($onlineId === null || $onlineId === '') {
            return self::buildPath(RouteName::Game->value, $gameSlug);
        }

        return self::gamePlayerPath($gameSlug, $onlineId);
    }

    private static function buildPath(string ...$segments): string
    {
        $path = '/' . implode('/', $segments);

        return Uri\Rfc3986\Uri::parse($path)?->toRawString() ?? $path;
    }
}
