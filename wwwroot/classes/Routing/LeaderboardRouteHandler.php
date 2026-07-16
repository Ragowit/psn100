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
        $view = array_first($segments) ?? '';

        return match ($view) {
            'main', 'trophy' => RouteResult::include('leaderboard_main.php'),
            'rarity' => RouteResult::include('leaderboard_rarity.php'),
            'in-game-rarity' => RouteResult::include('leaderboard_in_game_rarity.php'),
            default => RouteResult::redirect(self::DEFAULT_REDIRECT),
        };
    }
}
