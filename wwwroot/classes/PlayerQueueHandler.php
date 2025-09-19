<?php

class PlayerQueueHandler
{
    private PlayerQueueService $service;

    public function __construct(PlayerQueueService $service)
    {
        $this->service = $service;
    }

    public function handleAddToQueueRequest(array $requestData, array $serverData): string
    {
        $playerName = $this->service->sanitizePlayerName($requestData['q'] ?? '');
        $ipAddress = $this->service->sanitizeIpAddress($serverData['REMOTE_ADDR'] ?? '');

        if ($playerName === '') {
            return "PSN name can't be empty.";
        }

        $cheaterAccountId = $this->service->getCheaterAccountId($playerName);
        if ($cheaterAccountId !== null) {
            return $this->createCheaterMessage($playerName, $cheaterAccountId);
        }

        if ($this->service->hasReachedIpSubmissionLimit($ipAddress)) {
            return $this->createQueueLimitMessage();
        }

        if (!$this->service->isValidPlayerName($playerName)) {
            return "PSN name must contain between three and 16 characters, and can consist of letters, numbers, hyphens (-) and underscores (_).";
        }

        $this->service->addPlayerToQueue($playerName, $ipAddress);

        $playerLink = $this->createPlayerLink($playerName);

        return $this->createSpinnerMessage("{$playerLink} is being added to the queue.");
    }

    public function handleQueuePositionRequest(array $requestData, array $serverData): string
    {
        $playerName = $this->service->sanitizePlayerName($requestData['q'] ?? '');
        $ipAddress = $this->service->sanitizeIpAddress($serverData['REMOTE_ADDR'] ?? '');

        if ($playerName === '') {
            return "PSN name can't be empty.";
        }

        if ($this->service->hasReachedIpSubmissionLimit($ipAddress)) {
            return $this->createQueueLimitMessage();
        }

        if (!$this->service->isValidPlayerName($playerName)) {
            return "PSN name must contain between three and 16 characters, and can consist of letters, numbers, hyphens (-) and underscores (_).";
        }

        $playerData = $this->service->getPlayerStatusData($playerName);
        if ($this->service->isCheaterStatus($playerData['status'])) {
            return $this->createCheaterMessage($playerName, $playerData['account_id']);
        }

        if ($this->service->isPlayerBeingScanned($playerName)) {
            $playerLink = $this->createPlayerLink($playerName);

            return $this->createSpinnerMessage("{$playerLink} is currently being scanned.");
        }

        $position = $this->service->getQueuePosition($playerName);
        if ($position !== null) {
            $playerLink = $this->createPlayerLink($playerName);
            $positionText = $this->service->escapeHtml((string) $position);

            return $this->createSpinnerMessage("{$playerLink} is in the update queue, currently in position {$positionText}.");
        }

        $playerLink = $this->createPlayerLink($playerName);

        return "{$playerLink} has been updated!";
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
