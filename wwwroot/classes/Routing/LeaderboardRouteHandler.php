<?php

declare(strict_types=1);

require_once __DIR__ . '/RouteHandlerInterface.php';
require_once __DIR__ . '/../LeaderboardView.php';

final readonly class LeaderboardRouteHandler implements RouteHandlerInterface
{
    private const string DEFAULT_REDIRECT = '/leaderboard/trophy';
    private const string DIFFICULTY_PATH = '/leaderboard/difficulty';

    /**
     * @param list<string> $segments
     */
    #[\Override]
    public function handle(array $segments): RouteResult
    {
        $segment = array_first($segments) ?? '';

        if (LeaderboardView::isLegacyPath($segment)) {
            return RouteResult::redirect(self::legacyDifficultyRedirectLocation());
        }

        $view = LeaderboardView::tryFrom($segment);

        return $view !== null
            ? RouteResult::include($view->includeFile())
            : RouteResult::redirect(self::DEFAULT_REDIRECT);
    }

    private static function legacyDifficultyRedirectLocation(): string
    {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        if (!is_string($queryString) || $queryString === '') {
            return self::DIFFICULTY_PATH;
        }

        return self::DIFFICULTY_PATH . '?' . $queryString;
    }
}
