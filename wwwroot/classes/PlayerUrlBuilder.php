<?php

declare(strict_types=1);

final class PlayerUrlBuilder
{
    public static function playerPath(string $onlineId): string
    {
        return '/player/' . rawurlencode($onlineId);
    }

    public static function playerReportPath(string $onlineId): string
    {
        return self::playerPath($onlineId) . '/report';
    }

    public static function gamePlayerPath(string $gameSlug, string $onlineId): string
    {
        return '/game/' . $gameSlug . '/' . rawurlencode($onlineId);
    }

    public static function gamePath(string $gameSlug, ?string $onlineId = null): string
    {
        if ($onlineId === null || $onlineId === '') {
            return '/game/' . $gameSlug;
        }

        return self::gamePlayerPath($gameSlug, $onlineId);
    }
}
