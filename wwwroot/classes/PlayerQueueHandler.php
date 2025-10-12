<?php

require_once __DIR__ . '/PlayerQueueRequest.php';
require_once __DIR__ . '/PlayerQueueResponse.php';
require_once __DIR__ . '/PlayerQueueResponseFactory.php';

class PlayerQueueHandler
{
    private PlayerQueueService $service;

    private PlayerQueueResponseFactory $responseFactory;

    public function __construct(PlayerQueueService $service, ?PlayerQueueResponseFactory $responseFactory = null)
    {
        $this->service = $service;
        $this->responseFactory = $responseFactory ?? new PlayerQueueResponseFactory($service);
    }

    public function handleAddToQueueRequest(PlayerQueueRequest $request): PlayerQueueResponse
    {
        if ($request->isPlayerNameEmpty()) {
            return $this->responseFactory->createEmptyNameResponse();
        }

        $playerName = $request->getPlayerName();
        $ipAddress = $request->getIpAddress();

        $cheaterAccountId = $this->service->getCheaterAccountId($playerName);
        if ($cheaterAccountId !== null) {
            return $this->responseFactory->createCheaterResponse($playerName, $cheaterAccountId);
        }

        if ($this->service->hasReachedIpSubmissionLimit($ipAddress)) {
            return $this->responseFactory->createQueueLimitResponse();
        }

        if (!$this->service->isValidPlayerName($playerName)) {
            return $this->responseFactory->createInvalidNameResponse();
        }

        $this->service->addPlayerToQueue($playerName, $ipAddress);

        return $this->responseFactory->createQueuedForAdditionResponse($playerName);
    }

    public function handleQueuePositionRequest(PlayerQueueRequest $request): PlayerQueueResponse
    {
        if ($request->isPlayerNameEmpty()) {
            return $this->responseFactory->createEmptyNameResponse();
        }

        $playerName = $request->getPlayerName();
        $ipAddress = $request->getIpAddress();

        if ($this->service->hasReachedIpSubmissionLimit($ipAddress)) {
            return $this->responseFactory->createQueueLimitResponse();
        }

        if (!$this->service->isValidPlayerName($playerName)) {
            return $this->responseFactory->createInvalidNameResponse();
        }

        $playerData = $this->service->getPlayerStatusData($playerName);
        if ($this->service->isCheaterStatus($playerData['status'])) {
            return $this->responseFactory->createCheaterResponse($playerName, $playerData['account_id']);
        }

        if ($this->service->isPlayerBeingScanned($playerName)) {
            return $this->responseFactory->createQueuedForScanResponse($playerName);
        }

        $position = $this->service->getQueuePosition($playerName);
        if ($position !== null) {
            return $this->responseFactory->createQueuePositionResponse($playerName, $position);
        }

        return $this->responseFactory->createQueueCompleteResponse($playerName);
    }
}
