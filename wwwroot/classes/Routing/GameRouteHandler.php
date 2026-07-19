<?php

declare(strict_types=1);

require_once __DIR__ . '/../GameRepository.php';
require_once __DIR__ . '/../PsnOnlineIdValidator.php';
require_once __DIR__ . '/RouteHandlerInterface.php';

final readonly class GameRouteHandler implements RouteHandlerInterface
{
    public function __construct(
        final private GameRepository $gameRepository,
        final private string $includeFile,
        final private string $redirectPath,
        final private ?string $missingSegmentInclude = null,
    ) {
    }

    /**
     * @param list<string> $segments
     */
    #[\Override]
    public function handle(array $segments): RouteResult
    {
        if ((array_first($segments) ?? '') === '') {
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

        $playerSegment = array_first($segments);
        $player = is_string($playerSegment) && PsnOnlineIdValidator::isValidOnlineId($playerSegment)
            ? $playerSegment
            : null;

        return RouteResult::include(
            $this->includeFile,
            [
                'gameId' => $gameId,
                'player' => $player,
            ]
        );
    }
}
