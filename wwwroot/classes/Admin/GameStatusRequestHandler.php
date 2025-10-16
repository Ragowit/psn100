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
            return GameStatusRequestResult::error('<p>Invalid game ID provided.</p>');
        }

        $status = $request->getPostInt('status');
        if ($status === null) {
            return GameStatusRequestResult::error('<p>Invalid status provided.</p>');
        }

        try {
            $statusText = $this->gameStatusService->updateGameStatus($gameId, $status);
            $message = $this->formatSuccessMessage($gameId, $statusText);

            return GameStatusRequestResult::success($message);
        } catch (InvalidArgumentException $exception) {
            $message = $this->escapeMessage($exception->getMessage());

            return GameStatusRequestResult::error('<p>' . $message . '</p>');
        } catch (Throwable $exception) {
            return GameStatusRequestResult::error('<p>Failed to update game status. Please try again.</p>');
        }
    }

    private function formatSuccessMessage(int $gameId, string $statusText): string
    {
        $gameIdText = $this->escapeMessage((string) $gameId);
        $statusTextEscaped = $this->escapeMessage($statusText);

        return sprintf(
            '<p>Game %s is now set as %s. All affected players will be updated soon, and ranks updated the next whole hour.</p>',
            $gameIdText,
            $statusTextEscaped
        );
    }

    private function escapeMessage(string $message): string
    {
        return htmlentities($message, ENT_QUOTES, 'UTF-8');
    }
}
