<?php

declare(strict_types=1);

require_once __DIR__ . '/RouteHandlerInterface.php';
require_once __DIR__ . '/../LeaderboardView.php';

final readonly class LeaderboardRouteHandler implements RouteHandlerInterface
{
    private const string DEFAULT_REDIRECT = '/leaderboard/trophy';

    /**
     * @param list<string> $segments
     */
    #[\Override]
    public function handle(array $segments): RouteResult
    {
        $view = LeaderboardView::tryFrom(array_first($segments) ?? '');

        return $view !== null
            ? RouteResult::include($view->includeFile())
            : RouteResult::redirect(self::DEFAULT_REDIRECT);
    }
}
