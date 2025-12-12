<?php

declare(strict_types=1);

require_once __DIR__ . '/../TrophyRepository.php';
require_once __DIR__ . '/RouteHandlerInterface.php';

final readonly class TrophyRouteHandler implements RouteHandlerInterface
{
    public function __construct(private TrophyRepository $trophyRepository)
    {
    }

    /**
     * @param list<string> $segments
     */
    #[\Override]
    public function handle(array $segments): RouteResult
    {
        if (!isset($segments[0]) || $segments[0] === '') {
            return RouteResult::include('trophies.php');
        }

        $trophySegment = array_shift($segments);
        $trophyId = $this->trophyRepository->findIdFromSegment($trophySegment);

        if ($trophyId === null) {
            return RouteResult::redirect('/trophy/');
        }

        $player = $segments[0] ?? null;

        return RouteResult::include(
            'trophy.php',
            [
                'trophyId' => $trophyId,
                'player' => $player,
            ]
        );
    }
}
