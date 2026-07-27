<?php

declare(strict_types=1);

require_once __DIR__ . '/RouteResult.php';
require_once __DIR__ . '/RouteName.php';
require_once __DIR__ . '/GameRepository.php';
require_once __DIR__ . '/TrophyRepository.php';
require_once __DIR__ . '/PlayerRepository.php';
require_once __DIR__ . '/Routing/RouteHandlerInterface.php';
require_once __DIR__ . '/Routing/HomeRouteHandler.php';
require_once __DIR__ . '/Routing/SimpleRouteHandler.php';
require_once __DIR__ . '/Routing/GameRouteHandler.php';
require_once __DIR__ . '/Routing/LeaderboardRouteHandler.php';
require_once __DIR__ . '/Routing/PlayerRouteHandler.php';
require_once __DIR__ . '/Routing/TrophyRouteHandler.php';

class Router
{
    private readonly RouteHandlerInterface $defaultHandler;

    /**
     * @var array<string, RouteHandlerInterface>
     */
    private readonly array $routeHandlers;

    public function __construct(
        GameRepository $gameRepository,
        TrophyRepository $trophyRepository,
        PlayerRepository $playerRepository
    ) {
        $this->defaultHandler = new HomeRouteHandler('home.php');
        $this->routeHandlers = [
            RouteName::About->value => new SimpleRouteHandler('about.php', '/about/'),
            RouteName::Avatar->value => new SimpleRouteHandler('avatars.php', '/avatar/'),
            RouteName::Changelog->value => new SimpleRouteHandler('changelog.php', '/changelog/'),
            RouteName::Game->value => new GameRouteHandler($gameRepository, 'game.php', '/game/', 'games.php'),
            RouteName::GameHistory->value => new GameRouteHandler($gameRepository, 'game_history.php', '/game/'),
            RouteName::GameLeaderboard->value => new GameRouteHandler($gameRepository, 'game_leaderboard.php', '/game/'),
            RouteName::GameRecentPlayers->value => new GameRouteHandler($gameRepository, 'game_recent_players.php', '/game/'),
            RouteName::Leaderboard->value => new LeaderboardRouteHandler(),
            RouteName::Player->value => new PlayerRouteHandler($playerRepository),
            RouteName::Trophy->value => new TrophyRouteHandler($trophyRepository),
        ];
    }

    #[\NoDiscard]
    public function dispatch(string $requestUri): RouteResult
    {
        $normalizedPath = (Uri\Rfc3986\Uri::parse($requestUri)?->getPath() ?? '')
            |> (fn (string $path): string => trim($path, '/'));

        if ($normalizedPath === '') {
            return $this->defaultHandler->handle([]);
        }

        $segments = explode('/', $normalizedPath);
        $routeName = RouteName::tryFrom(array_first($segments) ?? '');

        if ($routeName === null) {
            return RouteResult::notFound();
        }

        $handler = $this->routeHandlers[$routeName->value] ?? null;

        if (!$handler instanceof RouteHandlerInterface) {
            return RouteResult::notFound();
        }

        return $handler->handle(array_slice($segments, 1));
    }
}
