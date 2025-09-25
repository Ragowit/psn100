<?php

declare(strict_types=1);

require_once __DIR__ . '/GameResetRequestResult.php';
require_once __DIR__ . '/../GameResetService.php';

class GameResetRequestHandler
{
    private GameResetService $gameResetService;

    public function __construct(GameResetService $gameResetService)
    {
        $this->gameResetService = $gameResetService;
    }

    /**
     * @param array<string, mixed> $postData
     */
    public function handleRequest(string $requestMethod, array $postData): GameResetRequestResult
    {
        if (strtoupper($requestMethod) !== 'POST') {
            return GameResetRequestResult::empty();
        }

        $gameId = $this->parsePositiveInt($postData['game'] ?? null);
        if ($gameId === null) {
            return GameResetRequestResult::error('<p>Please provide a valid game ID.</p>');
        }

        $action = $this->parseAction($postData['status'] ?? null);
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

    private function parsePositiveInt(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        if ($filtered === false) {
            return null;
        }

        $number = (int) $filtered;
        if ($number <= 0) {
            return null;
        }

        return $number;
    }

    private function parseAction(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        if ($filtered === false) {
            return null;
        }

        $action = (int) $filtered;
        if (!in_array($action, [0, 1], true)) {
            return null;
        }

        return $action;
    }

    private function escape(string $value): string
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8');
    }
}
