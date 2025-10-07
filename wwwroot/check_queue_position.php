<?php

declare(strict_types=1);

require_once 'init.php';
require_once 'classes/PlayerQueueController.php';

$controller = PlayerQueueController::create($database);

$requestData = $_REQUEST ?? [];
$serverData = $_SERVER ?? [];

$response = $controller->handleQueuePosition($requestData, $serverData);

header('Content-Type: application/json');

try {
    echo json_encode($response->toArray(), JSON_THROW_ON_ERROR);
} catch (JsonException) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred while encoding the response.',
        'shouldPoll' => false,
    ]);
}
