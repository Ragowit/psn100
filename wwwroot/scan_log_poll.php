<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/classes/AboutPageService.php';
require_once __DIR__ . '/classes/AboutPagePlayerArraySerializer.php';
require_once __DIR__ . '/classes/JsonResponseEmitter.php';

$jsonResponder = new JsonResponseEmitter();
$aboutPageService = new AboutPageService($database, $utility);

$limit = 30;
if (isset($_GET['limit'])) {
    $requestedLimit = filter_var($_GET['limit'], FILTER_VALIDATE_INT, [
        'options' => [
            'default' => $limit,
            'min_range' => 1,
            'max_range' => 100,
        ],
    ]);

    if ($requestedLimit !== false) {
        $limit = $requestedLimit;
    }
}

try {
    $scanSummary = $aboutPageService->getScanSummary();
    $scanLogPlayers = $aboutPageService->getScanLogPlayers($limit);

    $jsonResponder->respond([
        'status' => 'ok',
        'summary' => [
            'scannedPlayers' => $scanSummary->getScannedPlayers(),
            'newPlayers' => $scanSummary->getNewPlayers(),
        ],
        'players' => AboutPagePlayerArraySerializer::serializeCollection($scanLogPlayers),
    ]);
} catch (Throwable $exception) {
    $jsonResponder->respond([
        'status' => 'error',
        'message' => 'Unable to load scan log data at this time.',
    ], 500);
}
