<?php

require_once __DIR__ . '/PlayerQueueRequest.php';
require_once __DIR__ . '/PlayerQueueResponse.php';

class PlayerQueueHandler
{
    private PlayerQueueService $service;

    public function __construct(PlayerQueueService $service)
    {
        $this->service = $service;
    }

    public function handleAddToQueueRequest(PlayerQueueRequest $request): PlayerQueueResponse
    {
        if ($request->isPlayerNameEmpty()) {
            return PlayerQueueResponse::error("PSN name can't be empty.");
        }

        $playerName = $request->getPlayerName();
        $ipAddress = $request->getIpAddress();

        $cheaterAccountId = $this->service->getCheaterAccountId($playerName);
        if ($cheaterAccountId !== null) {
            return PlayerQueueResponse::error($this->createCheaterMessage($playerName, $cheaterAccountId));
        }

        if ($this->service->hasReachedIpSubmissionLimit($ipAddress)) {
            return PlayerQueueResponse::error($this->createQueueLimitMessage());
        }

        if (!$this->service->isValidPlayerName($playerName)) {
            return PlayerQueueResponse::error(
                "PSN name must contain between three and 16 characters, and can consist of letters, numbers, hyphens (-) and underscores (_)."
            );
        }

        $this->service->addPlayerToQueue($playerName, $ipAddress);

        $playerLink = $this->createPlayerLink($playerName);

        return PlayerQueueResponse::queued($this->createSpinnerMessage("{$playerLink} is being added to the queue."));
    }

    public function handleQueuePositionRequest(PlayerQueueRequest $request): PlayerQueueResponse
    {
        if ($request->isPlayerNameEmpty()) {
            return PlayerQueueResponse::error("PSN name can't be empty.");
        }

        $playerName = $request->getPlayerName();
        $ipAddress = $request->getIpAddress();

        if ($this->service->hasReachedIpSubmissionLimit($ipAddress)) {
            return PlayerQueueResponse::error($this->createQueueLimitMessage());
        }

        if (!$this->service->isValidPlayerName($playerName)) {
            return PlayerQueueResponse::error(
                "PSN name must contain between three and 16 characters, and can consist of letters, numbers, hyphens (-) and underscores (_)."
            );
        }

        $playerData = $this->service->getPlayerStatusData($playerName);
        if ($this->service->isCheaterStatus($playerData['status'])) {
            return PlayerQueueResponse::error($this->createCheaterMessage($playerName, $playerData['account_id']));
        }

        if ($this->service->isPlayerBeingScanned($playerName)) {
            $playerLink = $this->createPlayerLink($playerName);

            return PlayerQueueResponse::queued($this->createSpinnerMessage("{$playerLink} is currently being scanned."));
        }

        $position = $this->service->getQueuePosition($playerName);
        if ($position !== null) {
            $playerLink = $this->createPlayerLink($playerName);
            $positionText = $this->service->escapeHtml((string) $position);

            return PlayerQueueResponse::queued(
                $this->createSpinnerMessage("{$playerLink} is in the update queue, currently in position {$positionText}.")
            );
        }

        $playerLink = $this->createPlayerLink($playerName);

        return PlayerQueueResponse::complete("{$playerLink} has been updated!");
    }

    private function createPlayerLink(string $playerName): string
    {
        $escapedPlayerName = $this->service->escapeHtml($playerName);

        return '<a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/player/' . $escapedPlayerName . '">' . $escapedPlayerName . '</a>';
    }

    private function createSpinnerMessage(string $message): string
    {
        return $message . "\n<div class=\"spinner-border\" role=\"status\">\n    <span class=\"visually-hidden\">Loading...</span>\n</div>";
    }

    private function createCheaterMessage(string $playerName, ?string $accountId): string
    {
        $playerLink = $this->createPlayerLink($playerName);
        $accountIdValue = $accountId ?? '';
        $playerQuery = rawurlencode($playerName);
        $accountQuery = rawurlencode((string) $accountIdValue);
        $disputeUrl = 'https://github.com/Ragowit/psn100/issues?q=label%3Acheater+' . $playerQuery . '+OR+' . $accountQuery;
        $disputeLink = '<a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="' . $this->service->escapeHtml($disputeUrl) . '">Dispute</a>';

        return "Player '{$playerLink}' is tagged as a cheater and won't be scanned. {$disputeLink}?";
    }

    private function createQueueLimitMessage(): string
    {
        return 'You have already entered ' . PlayerQueueService::MAX_QUEUE_SUBMISSIONS_PER_IP . ' players into the queue. Please wait a while.';
    }
}
