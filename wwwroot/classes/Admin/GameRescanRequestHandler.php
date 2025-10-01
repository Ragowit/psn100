<?php

declare(strict_types=1);

class GameRescanRequestHandler
{
    private GameRescanService $gameRescanService;

    public function __construct(GameRescanService $gameRescanService)
    {
        $this->gameRescanService = $gameRescanService;
    }

    /**
     * @param array<string, mixed> $postData
     * @param array<string, mixed> $serverData
     */
    public function handleRequest(array $postData, array $serverData): void
    {
        $method = strtoupper((string) ($serverData['REQUEST_METHOD'] ?? ''));
        if ($method !== 'POST') {
            $this->sendJsonResponse(405, [
                'success' => false,
                'error' => 'Method not allowed.',
            ]);

            return;
        }

        $gameId = $this->resolveGameId($postData['game'] ?? null);

        if ($gameId === null) {
            $this->sendJsonResponse(400, [
                'success' => false,
                'error' => 'Please provide a valid game id.',
            ]);

            return;
        }

        $this->prepareStreamResponse();

        $this->sendEvent([
            'type' => 'progress',
            'progress' => 0,
            'message' => 'Preparing rescan…',
        ]);

        try {
            $message = $this->gameRescanService->rescan($gameId, function (int $percent, string $message): void {
                $this->sendEvent([
                    'type' => 'progress',
                    'progress' => $percent,
                    'message' => $message,
                ]);
            });

            $this->sendEvent([
                'type' => 'complete',
                'success' => true,
                'progress' => 100,
                'message' => $message,
            ]);
        } catch (\RuntimeException $exception) {
            http_response_code(200);

            $this->sendEvent([
                'type' => 'error',
                'success' => false,
                'progress' => 100,
                'error' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            http_response_code(200);
            error_log($exception->getMessage());

            $this->sendEvent([
                'type' => 'error',
                'success' => false,
                'progress' => 100,
                'error' => 'An unexpected error occurred while rescanning the game.',
            ]);
        }
    }

    private function resolveGameId(mixed $rawGameId): ?int
    {
        if (is_array($rawGameId)) {
            return null;
        }

        $rawGameId = trim((string) $rawGameId);

        if ($rawGameId === '' || !ctype_digit($rawGameId)) {
            return null;
        }

        return (int) $rawGameId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendEvent(array $payload): void
    {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE), "\n";

        // Ensure enough data is flushed to the client for intermediaries that buffer
        // small chunks (for example, when using FastCGI). The extra whitespace is
        // ignored by the client, but it helps deliver each progress update promptly.
        echo str_repeat(' ', 2048), "\n";

        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }

    private function prepareStreamResponse(): void
    {
        set_time_limit(0);
        ignore_user_abort(true);

        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_implicit_flush(true);
    }

    private function sendJsonResponse(int $statusCode, array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
