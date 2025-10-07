<?php

declare(strict_types=1);

require_once __DIR__ . '/../PlayerRepository.php';
require_once __DIR__ . '/RouteHandlerInterface.php';

class PlayerRouteHandler implements RouteHandlerInterface
{
    private PlayerRepository $playerRepository;

    public function __construct(PlayerRepository $playerRepository)
    {
        $this->playerRepository = $playerRepository;
    }

    /**
     * @param list<string> $segments
     */
    public function handle(array $segments): RouteResult
    {
        if (!isset($segments[0]) || $segments[0] === '') {
            return RouteResult::redirect('/leaderboard/trophy');
        }

        $onlineId = array_shift($segments) ?? '';
        $accountId = $this->playerRepository->findAccountIdByOnlineId($onlineId);

        if ($accountId === null) {
            return RouteResult::redirect('/player/');
        }

        $player = $this->playerRepository->fetchPlayerByAccountId($accountId);

        if (!is_array($player) || $player === []) {
            return RouteResult::redirect('/player/');
        }

        $view = $segments !== [] ? (string) array_shift($segments) : '';

        $variables = [
            'accountId' => $accountId,
            'player' => $player,
            'onlineId' => $onlineId,
        ];

        switch ($view) {
            case '':
                return RouteResult::include('player.php', $variables);
            case 'advisor':
                return RouteResult::include('player_advisor.php', $variables);
            case 'log':
                return RouteResult::include('player_log.php', $variables);
            case 'random':
                return RouteResult::include('player_random.php', $variables);
            case 'report':
                return RouteResult::include('player_report.php', $variables);
            default:
                return RouteResult::redirect('/player/' . $onlineId);
        }
    }
}
