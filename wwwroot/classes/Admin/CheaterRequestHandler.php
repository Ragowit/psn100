<?php

declare(strict_types=1);

require_once __DIR__ . '/CheaterRequestResult.php';
require_once __DIR__ . '/CheaterService.php';

class CheaterRequestHandler
{
    private CheaterService $cheaterService;

    public function __construct(CheaterService $cheaterService)
    {
        $this->cheaterService = $cheaterService;
    }

    /**
     * @param array<string, mixed> $postData
     */
    public function handle(string $requestMethod, array $postData): CheaterRequestResult
    {
        if (strtoupper($requestMethod) !== 'POST') {
            return CheaterRequestResult::empty();
        }

        $onlineId = $this->sanitizeOnlineId($postData['player'] ?? null);

        if ($onlineId === '') {
            return CheaterRequestResult::error('<p class="text-danger">Online ID cannot be empty.</p>');
        }

        try {
            $this->cheaterService->markPlayerAsCheater($onlineId);
        } catch (InvalidArgumentException $exception) {
            $message = $this->escapeHtml($exception->getMessage());

            return CheaterRequestResult::error('<p class="text-danger">' . $message . '</p>');
        } catch (Throwable $exception) {
            return CheaterRequestResult::error('<p class="text-danger">An unexpected error occurred while updating the player.</p>');
        }

        $player = $this->escapeHtml($onlineId);
        $message = sprintf('<p>Player %s is now tagged as a cheater.</p>', $player);

        return CheaterRequestResult::success($message);
    }

    private function sanitizeOnlineId(mixed $onlineId): string
    {
        return trim((string) ($onlineId ?? ''));
    }

    private function escapeHtml(string $value): string
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8');
    }
}
