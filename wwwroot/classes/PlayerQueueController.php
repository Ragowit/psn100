<?php

declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/PlayerQueueService.php';
require_once __DIR__ . '/PlayerQueueHandler.php';

class PlayerQueueController
{
    private PlayerQueueHandler $handler;

    public function __construct(PlayerQueueHandler $handler)
    {
        $this->handler = $handler;
    }

    public static function create(Database $database): self
    {
        $service = new PlayerQueueService($database);
        $handler = new PlayerQueueHandler($service);

        return new self($handler);
    }

    public function handleAddToQueue(array $requestData, array $serverData): string
    {
        return $this->handler->handleAddToQueueRequest($requestData, $serverData);
    }

    public function handleQueuePosition(array $requestData, array $serverData): string
    {
        return $this->handler->handleQueuePositionRequest($requestData, $serverData);
    }
}
