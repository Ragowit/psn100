<?php

declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/PlayerQueueController.php';
require_once __DIR__ . '/PlayerQueueResponse.php';
require_once __DIR__ . '/JsonResponseEmitter.php';

final class PlayerQueueEndpoint
{
    private function __construct(
        private readonly PlayerQueueController $controller,
        private readonly JsonResponseEmitter $jsonResponder,
    ) {
    }

    #[\NoDiscard]
    public static function create(PlayerQueueController $controller, JsonResponseEmitter $jsonResponder): self
    {
        return new self($controller, $jsonResponder);
    }

    #[\NoDiscard]
    public static function fromDatabase(Database $database): self
    {
        $controller = PlayerQueueController::create($database);
        $responder = new JsonResponseEmitter();

        return self::create($controller, $responder);
    }

    public function handleAddToQueue(array $requestData, array $serverData): void
    {
        $response = $this->controller->handleAddToQueue($requestData, $serverData);

        $this->jsonResponder->respond($response->toArray(), $response->getHttpStatusCode());
    }

    public function handleQueuePosition(array $requestData, array $serverData): void
    {
        $response = $this->controller->handleQueuePosition($requestData, $serverData);

        $this->jsonResponder->respond($response->toArray(), $response->getHttpStatusCode());
    }
}
