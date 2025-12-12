<?php

declare(strict_types=1);

require_once __DIR__ . '/RouteHandlerInterface.php';

final readonly class LeaderboardRouteHandler implements RouteHandlerInterface
{
    private const DEFAULT_REDIRECT = '/leaderboard/trophy';

    /**
     * @param list<string> $segments
     */
    #[\Override]
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
            case 'in-game-rarity':
                return RouteResult::include('leaderboard_in_game_rarity.php');
            default:
                return RouteResult::redirect(self::DEFAULT_REDIRECT);
        }
    }
}
