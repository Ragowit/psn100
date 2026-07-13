<?php

declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/PlayerQueueService.php';
require_once __DIR__ . '/PlayerQueueRequest.php';
require_once __DIR__ . '/PlayerQueueHandler.php';
require_once __DIR__ . '/PlayerQueueResponseFactory.php';
require_once __DIR__ . '/PlayerQueuePollTokenManager.php';
require_once __DIR__ . '/IpRateLimitService.php';

class PlayerQueueController
{
    public function __construct(
        private readonly PlayerQueueHandler $handler,
        private readonly ?IpRateLimitService $rateLimitService = null,
    ) {}

    public static function create(
        Database $database,
        ?PlayerQueuePollTokenManager $pollTokenManager = null,
        ?IpRateLimitService $rateLimitService = null,
    ): self {
        $service = new PlayerQueueService($database);
        $responseFactory = new PlayerQueueResponseFactory($service);
        $handler = new PlayerQueueHandler($service, $responseFactory, $pollTokenManager);
        $rateLimiter = $rateLimitService ?? new IpRateLimitService($database);

        return new self($handler, $rateLimiter);
    }

    public function handleAddToQueue(array $requestData, array $serverData): PlayerQueueResponse
    {
        $request = PlayerQueueRequest::fromArrays($requestData, $serverData);

        if (
            $this->rateLimitService !== null
            && !$this->rateLimitService->checkAndRecord(
                $request->getIpAddress(),
                IpRateLimitService::BUCKET_QUEUE_SUBMIT
            )
        ) {
            return PlayerQueueResponse::rateLimited(
                'Too many queue submissions. Please wait a moment and try again.'
            );
        }

        return $this->handler->handleAddToQueueRequest($request);
    }

    public function handleQueuePosition(array $requestData, array $serverData): PlayerQueueResponse
    {
        $request = PlayerQueueRequest::fromArrays($requestData, $serverData);

        if (
            $this->rateLimitService !== null
            && !$this->rateLimitService->checkAndRecord(
                $request->getIpAddress(),
                IpRateLimitService::BUCKET_QUEUE_POLL
            )
        ) {
            return PlayerQueueResponse::rateLimited(
                'Too many queue status requests. Please wait a moment and try again.'
            );
        }

        return $this->handler->handleQueuePositionRequest($request);
    }
}
