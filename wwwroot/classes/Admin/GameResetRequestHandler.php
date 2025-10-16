<?php

declare(strict_types=1);

require_once __DIR__ . '/AdminRequest.php';
require_once __DIR__ . '/GameResetRequestResult.php';
require_once __DIR__ . '/../GameResetService.php';

class GameResetRequestHandler
{
    private GameResetService $gameResetService;

    public function __construct(GameResetService $gameResetService)
    {
        $this->gameResetService = $gameResetService;
    }

    public function handleRequest(AdminRequest $request): GameResetRequestResult
    {
        if (!$request->isPost()) {
            return GameResetRequestResult::empty();
        }

        $gameId = $request->getPostPositiveInt('game');
        if ($gameId === null) {
            return GameResetRequestResult::error('<p>Please provide a valid game ID.</p>');
        }

        $action = $request->getPostIntInSet('status', [0, 1]);
        if ($action === null) {
            return GameResetRequestResult::error('<p>Please choose a valid action.</p>');
        }

        try {
            $message = $this->gameResetService->process($gameId, $action);

            return GameResetRequestResult::success('<p>' . $this->escape($message) . '</p>');
        } catch (InvalidArgumentException $exception) {
            $message = $this->escape($exception->getMessage());

            return GameResetRequestResult::error('<p>' . $message . '</p>');
        } catch (Throwable $exception) {
            return GameResetRequestResult::error('<p>Failed to process the request. Please try again.</p>');
        }
    }

    private function escape(string $value): string
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8');
    }
}
