<?php

declare(strict_types=1);

require_once __DIR__ . '/GameStatusRequestResult.php';
require_once __DIR__ . '/../GameStatusService.php';

class GameStatusRequestHandler
{
    private GameStatusService $gameStatusService;

    public function __construct(GameStatusService $gameStatusService)
    {
        $this->gameStatusService = $gameStatusService;
    }

    /**
     * @param array<string, mixed> $postData
     */
    public function handleRequest(string $requestMethod, array $postData): GameStatusRequestResult
    {
        if (strtoupper($requestMethod) !== 'POST') {
            return GameStatusRequestResult::empty();
        }

        $gameId = $this->parseNonNegativeInt($postData['game'] ?? null);
        if ($gameId === null) {
            return GameStatusRequestResult::error('<p>Invalid game ID provided.</p>');
        }

        $status = $this->parseInt($postData['status'] ?? null);
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

    private function parseInt(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT);

        return $filtered === false ? null : (int) $filtered;
    }

    private function parseNonNegativeInt(mixed $value): ?int
    {
        $number = $this->parseInt($value);

        if ($number === null || $number < 0) {
            return null;
        }

        return $number;
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
