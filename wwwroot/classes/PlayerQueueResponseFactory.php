<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerQueueService.php';
require_once __DIR__ . '/PlayerQueueResponse.php';
require_once __DIR__ . '/PlayerStatusNotice.php';
require_once __DIR__ . '/PlayerScanProgress.php';

final class PlayerQueueResponseFactory
{
    private const string EMPTY_NAME_MESSAGE = "PSN name can't be empty.";

    private const string INVALID_NAME_MESSAGE = "PSN name must contain between three and 16 characters, and can consist of letters, numbers, hyphens (-) and underscores (_).";

    public function __construct(private readonly PlayerQueueService $service)
    {
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

    public function createQueuedForScanResponse(
        string $playerName,
        ?PlayerScanProgress $scanProgress = null
    ): PlayerQueueResponse {
        $message = $this->createPlayerLink($playerName) . ' is currently being scanned.';

        if ($scanProgress !== null) {
            $message .= $this->createScanProgressMessage($scanProgress);
        }

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

    private function createScanProgressMessage(PlayerScanProgress $progress): string
    {
        $parts = [];

        $title = $progress->getTitle();
        if ($title !== null) {
            $progressTitle = $this->formatProgressTitle($title);

            if ($progressTitle !== '') {
                $parts[] = $progressTitle;
            }
        }

        $summary = $progress->getProgressSummary();
        if ($summary !== null) {
            $parts[] = $summary;
        }

        if ($parts === []) {
            return '';
        }

        $message = ' ' . implode(' ', $parts) . '.';

        $percentage = $progress->getPercentage();

        if ($percentage !== null) {
            $message .= sprintf(
                '<div class="progress mt-2" role="progressbar" aria-valuenow="%d" aria-valuemin="0" aria-valuemax="100">'
                . '<div class="progress-bar bg-primary" style="width: %d%%;"></div>'
                . '</div>',
                $percentage,
                $percentage
            );
        }

        return $message;
    }

    private function formatProgressTitle(string $title): string
    {
        $normalizedTitle = preg_replace('/\s+for\s+[^.]+\.?$/i', '', trim($title)) ?? '';
        $normalizedTitle = rtrim($normalizedTitle, " .\t\n\r\0\v");

        if ($normalizedTitle === '') {
            return '';
        }

        if (preg_match('/^(Updating|Fetching)\b/i', $normalizedTitle) === 1) {
            $actionText = lcfirst($normalizedTitle);

            return 'Currently ' . $this->service->escapeHtml($actionText);
        }

        return 'Currently scanning <strong>' . $this->service->escapeHtml($normalizedTitle) . '</strong>';
    }

    private function createCheaterMessage(string $playerName, ?string $accountId): string
    {
        $playerLink = $this->createPlayerLink($playerName);
        $disputeUrl = PlayerStatusNotice::createDisputeUrl($playerName, $accountId);
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
