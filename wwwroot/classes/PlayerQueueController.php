<?php

declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/PlayerQueueService.php';
require_once __DIR__ . '/PlayerQueueRequest.php';
require_once __DIR__ . '/PlayerQueueHandler.php';
require_once __DIR__ . '/PlayerQueueResponseFactory.php';

class PlayerQueueController
{
    public function __construct(private readonly PlayerQueueHandler $handler) {}

    public static function create(Database $database): self
    {
        $service = new PlayerQueueService($database);
        $responseFactory = new PlayerQueueResponseFactory($service);
        $handler = new PlayerQueueHandler($service, $responseFactory);

        return new self($handler);
    }

    public function handleAddToQueue(array $requestData, array $serverData): PlayerQueueResponse
    {
        $request = PlayerQueueRequest::fromArrays($requestData, $serverData);

        return $this->handler->handleAddToQueueRequest($request);
    }

    public function handleQueuePosition(array $requestData, array $serverData): PlayerQueueResponse
    {
        $request = PlayerQueueRequest::fromArrays($requestData, $serverData);

        return $this->handler->handleQueuePositionRequest($request);
    }
}
