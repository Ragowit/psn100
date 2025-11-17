<?php

declare(strict_types=1);

require_once __DIR__ . '/../ExecutionEnvironmentConfigurator.php';
require_once __DIR__ . '/../TrophyMergeService.php';
require_once __DIR__ . '/TrophyMergeRequestHandler.php';
require_once __DIR__ . '/CallableTrophyMergeProgressListener.php';

class TrophyMergeProcessor
{
    private TrophyMergeRequestHandler $requestHandler;

    public function __construct(TrophyMergeRequestHandler $requestHandler)
    {
        $this->requestHandler = $requestHandler;
    }

    public static function fromDatabase(PDO $database): self
    {
        $mergeService = new TrophyMergeService($database);
        $requestHandler = new TrophyMergeRequestHandler($mergeService);

        return new self($requestHandler);
    }

    /**
     * @param array<string, mixed> $postData
     * @param array<string, mixed> $serverData
     */
    public function processRequest(array $postData, array $serverData): void
    {
        $method = strtoupper((string) ($serverData['REQUEST_METHOD'] ?? ''));
        if ($method !== 'POST') {
            $this->sendJsonResponse(405, [
                'success' => false,
                'error' => 'Method not allowed.',
            ]);

            return;
        }

        $childId = $this->filterNumericValue($postData['child'] ?? null);
        $parentId = $this->filterNumericValue($postData['parent'] ?? null);
        $mergeMethod = strtolower((string) ($postData['method'] ?? 'order'));

        if ($childId === null || $parentId === null) {
            $this->sendJsonResponse(400, [
                'success' => false,
                'error' => 'Please provide numeric child and parent game ids.',
            ]);

            return;
        }

        http_response_code(200);
        $this->prepareStreamResponse();

        $this->sendEvent([
            'type' => 'progress',
            'progress' => 0,
            'message' => 'Preparing game merge…',
        ]);

        $progressListener = new CallableTrophyMergeProgressListener(function (int $percent, string $message): void {
            $this->sendEvent([
                'type' => 'progress',
                'progress' => $percent,
                'message' => $message,
            ]);
        });

        try {
            $resultMessage = $this->requestHandler->handleGameMergeWithProgress(
                [
                    'child' => (string) $childId,
                    'parent' => (string) $parentId,
                    'method' => $mergeMethod,
                ],
                $progressListener
            );

            $this->sendEvent([
                'type' => 'complete',
                'success' => true,
                'progress' => 100,
                'message' => $resultMessage,
            ]);
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            $this->sendEvent([
                'type' => 'error',
                'success' => false,
                'progress' => 100,
                'error' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            error_log($exception->getMessage());

            $this->sendEvent([
                'type' => 'error',
                'success' => false,
                'progress' => 100,
                'error' => 'An unexpected error occurred while merging the games.',
            ]);
        }
    }

    private function filterNumericValue(mixed $value): ?int
    {
        if (is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    protected function prepareStreamResponse(): void
    {
        ExecutionEnvironmentConfigurator::create()
            ->enableUnlimitedExecution()
            ->enableIgnoreUserAbort()
            ->configure();

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

    /**
     * @param array<string, mixed> $payload
     */
    protected function sendEvent(array $payload): void
    {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE), "\n";
        echo str_repeat(' ', 2048), "\n";

        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function sendJsonResponse(int $statusCode, array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
