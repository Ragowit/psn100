<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerQueueRequest.php';
require_once __DIR__ . '/PlayerQueueResponse.php';
require_once __DIR__ . '/PlayerQueueResponseFactory.php';
require_once __DIR__ . '/PlayerQueuePollTokenManager.php';
require_once __DIR__ . '/IpRateLimitService.php';
require_once __DIR__ . '/IpSubmissionLockUnavailableException.php';

class PlayerQueueHandler
{
    private readonly PlayerQueueResponseFactory $responseFactory;

    private readonly PlayerQueuePollTokenManager $pollTokenManager;

    public function __construct(
        private readonly PlayerQueueService $service,
        ?PlayerQueueResponseFactory $responseFactory = null,
        ?PlayerQueuePollTokenManager $pollTokenManager = null,
    ) {
        $this->responseFactory = $responseFactory ?? new PlayerQueueResponseFactory($service);
        $this->pollTokenManager = $pollTokenManager ?? new PlayerQueuePollTokenManager();
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

        if (!$this->service->isValidPlayerName($playerName)) {
            return $this->responseFactory->createInvalidNameResponse();
        }

        try {
            if (!$this->service->addPlayerToQueue($playerName, $ipAddress)) {
                return $this->responseFactory->createQueueLimitResponse();
            }
        } catch (IpSubmissionLockUnavailableException) {
            return $this->responseFactory->createBusyResponse();
        }

        $response = $this->responseFactory->createQueuedForAdditionResponse($playerName);

        if ($response->shouldPoll()) {
            return $response->withPollToken($this->pollTokenManager->issue($playerName));
        }

        return $response;
    }

    public function handleQueuePositionRequest(PlayerQueueRequest $request): PlayerQueueResponse
    {
        if ($request->isPlayerNameEmpty()) {
            return $this->responseFactory->createEmptyNameResponse();
        }

        $playerName = $request->getPlayerName();

        if (!$this->service->isValidPlayerName($playerName)) {
            return $this->responseFactory->createInvalidNameResponse();
        }

        if (!$this->pollTokenManager->validate($playerName, $request->getPollToken())) {
            return PlayerQueueResponse::error(
                'Your session has expired. Please reload the page and try again.'
            );
        }

        $playerData = $this->service->getPlayerStatusData($playerName);
        if ($playerData !== null && $this->service->isCheaterStatus($playerData['status'])) {
            return $this->responseFactory->createCheaterResponse($playerName, $playerData['account_id']);
        }

        $scanStatus = $this->service->getActiveScanStatus($playerName);
        if ($scanStatus !== null) {
            return $this->responseFactory->createQueuedForScanResponse(
                $playerName,
                $scanStatus->getProgress()
            );
        }

        $position = $this->service->getQueuePosition($playerName);
        if ($position !== null) {
            return $this->responseFactory->createQueuePositionResponse($playerName, $position);
        }

        if ($playerData === null) {
            return $this->responseFactory->createPlayerNotFoundResponse($playerName);
        }

        return $this->responseFactory->createQueueCompleteResponse($playerName);
    }
}
