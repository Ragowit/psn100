<?php

declare(strict_types=1);

require_once __DIR__ . '/../classes/ExecutionEnvironmentConfigurator.php';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../classes/TrophyMergeService.php';
require_once __DIR__ . '/../classes/Admin/TrophyMergeRequestHandler.php';
require_once __DIR__ . '/../classes/Admin/CallableTrophyMergeProgressListener.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));

if ($method !== 'POST') {
    sendJsonResponse(405, [
        'success' => false,
        'error' => 'Method not allowed.',
    ]);

    return;
}

$childId = filterNumericValue($_POST['child'] ?? null);
$parentId = filterNumericValue($_POST['parent'] ?? null);
$mergeMethod = strtolower((string) ($_POST['method'] ?? 'order'));

if ($childId === null || $parentId === null) {
    sendJsonResponse(400, [
        'success' => false,
        'error' => 'Please provide numeric child and parent game ids.',
    ]);

    return;
}

prepareStreamResponse();

sendEvent([
    'type' => 'progress',
    'progress' => 0,
    'message' => 'Preparing game merge…',
]);

$mergeService = new TrophyMergeService($database);
$requestHandler = new TrophyMergeRequestHandler($mergeService);
$progressListener = new CallableTrophyMergeProgressListener(function (int $percent, string $message): void {
    sendEvent([
        'type' => 'progress',
        'progress' => $percent,
        'message' => $message,
    ]);
});

try {
    $resultMessage = $requestHandler->handleGameMergeWithProgress(
        [
            'child' => (string) $childId,
            'parent' => (string) $parentId,
            'method' => $mergeMethod,
        ],
        $progressListener
    );

    sendEvent([
        'type' => 'complete',
        'success' => true,
        'progress' => 100,
        'message' => $resultMessage,
    ]);
} catch (InvalidArgumentException | RuntimeException $exception) {
    http_response_code(200);

    sendEvent([
        'type' => 'error',
        'success' => false,
        'progress' => 100,
        'error' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    http_response_code(200);
    error_log($exception->getMessage());

    sendEvent([
        'type' => 'error',
        'success' => false,
        'progress' => 100,
        'error' => 'An unexpected error occurred while merging the games.',
    ]);
}

/**
 * @param mixed $value
 */
function filterNumericValue($value): ?int
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

function prepareStreamResponse(): void
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
function sendEvent(array $payload): void
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
function sendJsonResponse(int $statusCode, array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}
