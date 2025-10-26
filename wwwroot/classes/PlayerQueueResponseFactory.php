<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerQueueService.php';
require_once __DIR__ . '/PlayerQueueResponse.php';

final class PlayerQueueResponseFactory
{
    private const EMPTY_NAME_MESSAGE = "PSN name can't be empty.";

    private const INVALID_NAME_MESSAGE = "PSN name must contain between three and 16 characters, and can consist of letters, numbers, hyphens (-) and underscores (_).";

    private PlayerQueueService $service;

    public function __construct(PlayerQueueService $service)
    {
        $this->service = $service;
    }

    public function createEmptyNameResponse(): PlayerQueueResponse
    {
        return PlayerQueueResponse::error(self::EMPTY_NAME_MESSAGE);
    }

    public function createInvalidNameResponse(): PlayerQueueResponse
    {
        return PlayerQueueResponse::error(self::INVALID_NAME_MESSAGE);
    }

    public function createQueueLimitResponse(): PlayerQueueResponse
    {
        return PlayerQueueResponse::error($this->createQueueLimitMessage());
    }

    public function createCheaterResponse(string $playerName, ?string $accountId): PlayerQueueResponse
    {
        return PlayerQueueResponse::error($this->createCheaterMessage($playerName, $accountId));
    }

    public function createQueuedForAdditionResponse(string $playerName): PlayerQueueResponse
    {
        $message = $this->createPlayerLink($playerName) . ' is being added to the queue.';

        return $this->createQueuedResponse($message);
    }

    public function createQueuedForScanResponse(string $playerName): PlayerQueueResponse
    {
        $message = $this->createPlayerLink($playerName) . ' is currently being scanned.';

        return $this->createQueuedResponse($message);
    }

    public function createQueuePositionResponse(string $playerName, int|string $position): PlayerQueueResponse
    {
        $positionText = $this->service->escapeHtml((string) $position);
        $message = $this->createPlayerLink($playerName)
            . ' is in the update queue, currently in position '
            . $positionText . '.';

        return $this->createQueuedResponse($message);
    }

    public function createQueueCompleteResponse(string $playerName): PlayerQueueResponse
    {
        $playerLink = $this->createPlayerLink($playerName);

        return PlayerQueueResponse::complete($playerLink . ' has been updated!');
    }

    public function createPlayerNotFoundResponse(string $playerName): PlayerQueueResponse
    {
        $playerLink = $this->createPlayerLink($playerName);

        return PlayerQueueResponse::error($playerLink . ' was not found. Please check the spelling and try again.');
    }

    private function createQueuedResponse(string $message): PlayerQueueResponse
    {
        return PlayerQueueResponse::queued($this->createSpinnerMessage($message));
    }

    private function createPlayerLink(string $playerName): string
    {
        $escapedPlayerName = $this->service->escapeHtml($playerName);
        $playerUrl = '/player/' . rawurlencode($playerName);
        $escapedPlayerUrl = $this->service->escapeHtml($playerUrl);

        return '<a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="'
            . $escapedPlayerUrl . '">' . $escapedPlayerName . '</a>';
    }

    private function createSpinnerMessage(string $message): string
    {
        return $message
            . "\n<div class=\"spinner-border\" role=\"status\">\n    <span class=\"visually-hidden\">Loading...</span>\n</div>";
    }

    private function createCheaterMessage(string $playerName, ?string $accountId): string
    {
        $playerLink = $this->createPlayerLink($playerName);
        $accountIdValue = $accountId ?? '';
        $playerQuery = rawurlencode($playerName);
        $accountQuery = rawurlencode((string) $accountIdValue);
        $disputeUrl = 'https://github.com/Ragowit/psn100/issues?q=label%3Acheater+' . $playerQuery . '+OR+' . $accountQuery;
        $disputeLink = '<a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="'
            . $this->service->escapeHtml($disputeUrl) . '">Dispute</a>';

        return "Player '{$playerLink}' is tagged as a cheater and won't be scanned. {$disputeLink}?";
    }

    private function createQueueLimitMessage(): string
    {
        return 'You have already entered ' . PlayerQueueService::MAX_QUEUE_SUBMISSIONS_PER_IP
            . ' players into the queue. Please wait a while.';
    }
}
