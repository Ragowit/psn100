<?php

declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/PlayerQueueController.php';
require_once __DIR__ . '/PlayerQueueResponse.php';

class PlayerQueueEndpoint
{
    private PlayerQueueController $controller;

    private function __construct(PlayerQueueController $controller)
    {
        $this->controller = $controller;
    }

    public static function fromDatabase(Database $database): self
    {
        $controller = PlayerQueueController::create($database);

        return new self($controller);
    }

    public function handleAddToQueue(array $requestData, array $serverData): void
    {
        $response = $this->controller->handleAddToQueue($requestData, $serverData);

        $this->sendJsonResponse($response);
    }

    public function handleQueuePosition(array $requestData, array $serverData): void
    {
        $response = $this->controller->handleQueuePosition($requestData, $serverData);

        $this->sendJsonResponse($response);
    }

    private function sendJsonResponse(PlayerQueueResponse $response): void
    {
        header('Content-Type: application/json');

        try {
            echo json_encode($response->toArray(), JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->renderEncodingError();
        }
    }

    private function renderEncodingError(): void
    {
        http_response_code(500);

        $fallbackPayload = [
            'status' => 'error',
            'message' => 'An unexpected error occurred while encoding the response.',
            'shouldPoll' => false,
        ];

        $encodedFallback = json_encode($fallbackPayload);
        if ($encodedFallback === false) {
            echo '{"status":"error","message":"An unexpected error occurred while encoding the response.","shouldPoll":false}';

            return;
        }

        echo $encodedFallback;
    }
}
