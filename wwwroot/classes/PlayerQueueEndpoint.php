<?php

declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/PlayerQueueController.php';
require_once __DIR__ . '/PlayerQueueResponse.php';
require_once __DIR__ . '/JsonResponseEmitter.php';

class PlayerQueueEndpoint
{
    private PlayerQueueController $controller;

    private JsonResponseEmitter $jsonResponder;

    private function __construct(PlayerQueueController $controller, JsonResponseEmitter $jsonResponder)
    {
        $this->controller = $controller;
        $this->jsonResponder = $jsonResponder;
    }

    public static function create(PlayerQueueController $controller, JsonResponseEmitter $jsonResponder): self
    {
        return new self($controller, $jsonResponder);
    }

    public static function fromDatabase(Database $database): self
    {
        $controller = PlayerQueueController::create($database);
        $responder = new JsonResponseEmitter();

        return self::create($controller, $responder);
    }

    public function handleAddToQueue(array $requestData, array $serverData): void
    {
        $response = $this->controller->handleAddToQueue($requestData, $serverData);

        $this->jsonResponder->respond($response);
    }

    public function handleQueuePosition(array $requestData, array $serverData): void
    {
        $response = $this->controller->handleQueuePosition($requestData, $serverData);

        $this->jsonResponder->respond($response);
    }
}
