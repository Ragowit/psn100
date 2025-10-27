<?php

declare(strict_types=1);

require_once __DIR__ . '/AdminRequest.php';
require_once __DIR__ . '/GameStatusRequestResult.php';
require_once __DIR__ . '/../GameStatusService.php';

class GameStatusRequestHandler
{
    private GameStatusService $gameStatusService;

    public function __construct(GameStatusService $gameStatusService)
    {
        $this->gameStatusService = $gameStatusService;
    }

    public function handleRequest(AdminRequest $request): GameStatusRequestResult
    {
        if (!$request->isPost()) {
            return GameStatusRequestResult::empty();
        }

        $gameId = $request->getPostNonNegativeInt('game');
        if ($gameId === null) {
            return GameStatusRequestResult::error('Invalid game ID provided.');
        }

        $status = $request->getPostInt('status');
        if ($status === null) {
            return GameStatusRequestResult::error('Invalid status provided.');
        }

        try {
            $statusText = $this->gameStatusService->updateGameStatus($gameId, $status);
            $message = $this->formatSuccessMessage($gameId, $statusText);

            return GameStatusRequestResult::success($message);
        } catch (InvalidArgumentException $exception) {
            return GameStatusRequestResult::error($exception->getMessage());
        } catch (Throwable $exception) {
            return GameStatusRequestResult::error('Failed to update game status. Please try again.');
        }
    }

    private function formatSuccessMessage(int $gameId, string $statusText): string
    {
        return sprintf(
            'Game %d is now set as %s. All affected players will be updated soon, and ranks updated the next whole hour.',
            $gameId,
            $statusText
        );
    }
}
