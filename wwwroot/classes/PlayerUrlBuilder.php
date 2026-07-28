<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerRouteView.php';
require_once __DIR__ . '/RouteName.php';
require_once __DIR__ . '/GameListSort.php';

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
        return self::playerViewPath($onlineId, PlayerRouteView::Report);
    }

    #[\NoDiscard]
    public static function playerViewPath(string $onlineId, PlayerRouteView $view): string
    {
        return self::buildPath(
            RouteName::Player->value,
            rawurlencode($onlineId),
            $view->value
        );
    }

    #[\NoDiscard]
    public static function gameAdvisorPath(string $onlineId): string
    {
        $path = '/' . RouteName::Game->value;
        $query = http_build_query(
            [
                'sort' => GameListSort::Completion->value,
                'filter' => 'true',
                'player' => $onlineId,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        return Uri\Rfc3986\Uri::parse($path)
            ?->withQuery($query)
            ->toRawString()
            ?? $path . '?' . $query;
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
