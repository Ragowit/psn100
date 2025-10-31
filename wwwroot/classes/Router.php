<?php

declare(strict_types=1);

require_once __DIR__ . '/RouteResult.php';
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
    private RouteHandlerInterface $defaultHandler;

    /**
     * @var array<string, RouteHandlerInterface>
     */
    private array $routeHandlers;

    public function __construct(
        GameRepository $gameRepository,
        TrophyRepository $trophyRepository,
        PlayerRepository $playerRepository
    ) {
        $this->defaultHandler = new HomeRouteHandler('home.php');
        $this->routeHandlers = [
            'about' => new SimpleRouteHandler('about.php', '/about/'),
            'avatar' => new SimpleRouteHandler('avatars.php', '/avatar/'),
            'changelog' => new SimpleRouteHandler('changelog.php', '/changelog/'),
            'game' => new GameRouteHandler($gameRepository, 'game.php', '/game/', 'games.php'),
            'game-history' => new GameRouteHandler($gameRepository, 'game_history.php', '/game/'),
            'game-leaderboard' => new GameRouteHandler($gameRepository, 'game_leaderboard.php', '/game/'),
            'game-recent-players' => new GameRouteHandler($gameRepository, 'game_recent_players.php', '/game/'),
            'leaderboard' => new LeaderboardRouteHandler(),
            'player' => new PlayerRouteHandler($playerRepository),
            'trophy' => new TrophyRouteHandler($trophyRepository),
        ];
    }

    public function dispatch(string $requestUri): RouteResult
    {
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '';
        $path = ltrim($path, '/');

        $segments = $path === '' ? [] : explode('/', $path);

        if ($segments === [] || $segments[0] === '') {
            return $this->defaultHandler->handle([]);
        }

        $route = array_shift($segments);

        $handler = $route !== null ? ($this->routeHandlers[$route] ?? null) : null;

        if (!$handler instanceof RouteHandlerInterface) {
            return RouteResult::notFound();
        }

        return $handler->handle($segments);
    }
}
