<?php

declare(strict_types=1);

require_once __DIR__ . '/../GameRepository.php';
require_once __DIR__ . '/RouteHandlerInterface.php';

class GameRouteHandler implements RouteHandlerInterface
{
    private GameRepository $gameRepository;

    private string $includeFile;

    private string $redirectPath;

    private ?string $missingSegmentInclude;

    public function __construct(
        GameRepository $gameRepository,
        string $includeFile,
        string $redirectPath,
        ?string $missingSegmentInclude = null
    ) {
        $this->gameRepository = $gameRepository;
        $this->includeFile = $includeFile;
        $this->redirectPath = $redirectPath;
        $this->missingSegmentInclude = $missingSegmentInclude;
    }

    /**
     * @param list<string> $segments
     */
    #[\Override]
    public function handle(array $segments): RouteResult
    {
        if (!isset($segments[0]) || $segments[0] === '') {
            if ($this->missingSegmentInclude !== null) {
                return RouteResult::include($this->missingSegmentInclude);
            }

            return RouteResult::redirect($this->redirectPath);
        }

        $gameSegment = array_shift($segments);
        $gameId = $this->gameRepository->findIdFromSegment($gameSegment);

        if ($gameId === null) {
            return RouteResult::redirect($this->redirectPath);
        }

        $player = $segments[0] ?? null;

        return RouteResult::include(
            $this->includeFile,
            [
                'gameId' => $gameId,
                'player' => $player,
            ]
        );
    }
}
