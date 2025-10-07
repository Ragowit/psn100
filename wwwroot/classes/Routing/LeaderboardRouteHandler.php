<?php

declare(strict_types=1);

require_once __DIR__ . '/RouteHandlerInterface.php';

class LeaderboardRouteHandler implements RouteHandlerInterface
{
    private const DEFAULT_REDIRECT = '/leaderboard/trophy';

    /**
     * @param list<string> $segments
     */
    public function handle(array $segments): RouteResult
    {
        if (!isset($segments[0]) || $segments[0] === '') {
            return RouteResult::redirect(self::DEFAULT_REDIRECT);
        }

        $view = array_shift($segments) ?? '';

        switch ($view) {
            case 'main':
            case 'trophy':
                return RouteResult::include('leaderboard_main.php');
            case 'rarity':
                return RouteResult::include('leaderboard_rarity.php');
            default:
                return RouteResult::redirect(self::DEFAULT_REDIRECT);
        }
    }
}
