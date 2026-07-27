<?php

declare(strict_types=1);

require_once __DIR__ . '/../PlayerRepository.php';
require_once __DIR__ . '/../PlayerRouteView.php';
require_once __DIR__ . '/../PlayerUrlBuilder.php';
require_once __DIR__ . '/RouteHandlerInterface.php';

final readonly class PlayerRouteHandler implements RouteHandlerInterface
{
    public function __construct(final private PlayerRepository $playerRepository)
    {
    }

    /**
     * @param list<string> $segments
     */
    #[\Override]
    public function handle(array $segments): RouteResult
    {
        if ((array_first($segments) ?? '') === '') {
            return RouteResult::redirect('/leaderboard/trophy');
        }

        $onlineIdSegment = array_first($segments) ?? '';
        $onlineId = rawurldecode($onlineIdSegment);
        $segments = array_slice($segments, 1);

        $accountId = $this->playerRepository->findAccountIdByOnlineId($onlineId);

        if ($accountId === null) {
            return RouteResult::redirect('/player/');
        }

        $player = $this->playerRepository->fetchPlayerByAccountId($accountId);

        if (!is_array($player) || $player === []) {
            return RouteResult::redirect('/player/');
        }

        $viewSegment = (string) (array_first($segments) ?? '');
        $variables = [
            'accountId' => $accountId,
            'player' => $player,
            'onlineId' => $onlineId,
        ];

        if ($viewSegment === '') {
            return RouteResult::include('player.php', $variables);
        }

        $view = PlayerRouteView::tryFrom($viewSegment);

        return $view !== null
            ? RouteResult::include($view->includeFile(), $variables)
            : RouteResult::redirect(PlayerUrlBuilder::playerPath($onlineId));
    }
}
