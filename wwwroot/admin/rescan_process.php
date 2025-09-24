<?php

declare(strict_types=1);

require_once '../vendor/autoload.php';
require_once '../init.php';
require_once '../classes/TrophyCalculator.php';
require_once '../classes/Admin/GameRescanService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed.'
    ]);
    exit;
}

$rawGameId = $_POST['game'] ?? '';

if (is_array($rawGameId)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Please provide a valid game id.'
    ]);
    exit;
}

$rawGameId = trim((string) $rawGameId);

if ($rawGameId === '' || !ctype_digit($rawGameId)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Please provide a valid game id.'
    ]);
    exit;
}

$gameId = (int) $rawGameId;

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

$sendEvent = static function (array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE), "\n";

    if (function_exists('ob_flush')) {
        @ob_flush();
    }

    flush();
};

$sendEvent([
    'type' => 'progress',
    'progress' => 0,
    'message' => 'Preparing rescan…',
]);

$trophyCalculator = new TrophyCalculator($database);
$gameRescanService = new GameRescanService($database, $trophyCalculator);

$progressCallback = static function (int $percent, string $message) use ($sendEvent): void {
    $sendEvent([
        'type' => 'progress',
        'progress' => $percent,
        'message' => $message,
    ]);
};

try {
    $message = $gameRescanService->rescan($gameId, $progressCallback);

    $sendEvent([
        'type' => 'complete',
        'success' => true,
        'progress' => 100,
        'message' => $message,
    ]);
} catch (RuntimeException $exception) {
    http_response_code(200);

    $sendEvent([
        'type' => 'error',
        'success' => false,
        'progress' => 100,
        'error' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    http_response_code(200);
    error_log($exception->getMessage());

    $sendEvent([
        'type' => 'error',
        'success' => false,
        'progress' => 100,
        'error' => 'An unexpected error occurred while rescanning the game.',
    ]);
}
