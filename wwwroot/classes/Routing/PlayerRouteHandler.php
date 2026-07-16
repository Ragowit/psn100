<?php

declare(strict_types=1);

require_once __DIR__ . '/../PlayerRepository.php';
require_once __DIR__ . '/RouteHandlerInterface.php';

final readonly class PlayerRouteHandler implements RouteHandlerInterface
{
    public function __construct(private PlayerRepository $playerRepository)
    {
    }

    /**
     * @param list<string> $segments
     */
    #[\Override]
    public function handle(array $segments): RouteResult
    {
        if (!isset($segments[0]) || $segments[0] === '') {
            return RouteResult::redirect('/leaderboard/trophy');
        }

        $onlineIdSegment = array_shift($segments) ?? '';
        $onlineId = rawurldecode($onlineIdSegment);

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

        return match ($view) {
            '' => RouteResult::include('player.php', $variables),
            'advisor' => RouteResult::include('player_advisor.php', $variables),
            'log' => RouteResult::include('player_log.php', $variables),
            'random' => RouteResult::include('player_random.php', $variables),
            'report' => RouteResult::include('player_report.php', $variables),
            'timeline' => RouteResult::include('player_timeline.php', $variables),
            default => RouteResult::redirect('/player/' . rawurlencode($onlineId)),
        };
    }
}
