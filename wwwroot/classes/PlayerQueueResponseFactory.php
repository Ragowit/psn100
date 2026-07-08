<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerQueueService.php';
require_once __DIR__ . '/PlayerQueueResponse.php';
require_once __DIR__ . '/PlayerStatusNotice.php';
require_once __DIR__ . '/PlayerScanProgress.php';
require_once __DIR__ . '/PlayerQueueMessageBuilder.php';
require_once __DIR__ . '/PlayerUrlBuilder.php';

final class PlayerQueueResponseFactory
{
    private const string EMPTY_NAME_MESSAGE = "PSN name can't be empty.";


    private const string BUSY_MESSAGE = 'The server is busy processing another request. Please try again in a moment.';

    public function __construct(private readonly PlayerQueueService $service)
    {
    }

    public function createEmptyNameResponse(): PlayerQueueResponse
    {
        return PlayerQueueResponse::error(self::EMPTY_NAME_MESSAGE);
    }

    public function createInvalidNameResponse(): PlayerQueueResponse
    {
        return PlayerQueueResponse::error(PlayerQueueService::INVALID_ONLINE_ID_MESSAGE);
    }

    public function createQueueLimitResponse(): PlayerQueueResponse
    {
        return PlayerQueueResponse::error($this->createQueueLimitMessage());
    }

    public function createBusyResponse(): PlayerQueueResponse
    {
        return PlayerQueueResponse::busy(self::BUSY_MESSAGE);
    }

    public function createCheaterResponse(string $playerName, ?string $accountId): PlayerQueueResponse
    {
        $messageParts = PlayerQueueMessageBuilder::create()
            ->text("Player '")
            ->playerLink($playerName)
            ->text("' is tagged as a cheater and won't be scanned. ")
            ->link(PlayerStatusNotice::createDisputeUrl($playerName, $accountId), 'Dispute')
            ->text('?')
            ->build();

        return PlayerQueueResponse::error($this->buildPlainText($messageParts), $messageParts);
    }

    public function createQueuedForAdditionResponse(string $playerName): PlayerQueueResponse
    {
        $message = PlayerQueueMessageBuilder::create()
            ->playerLink($playerName)
            ->text(' is being added to the queue.')
            ->spinner()
            ->build();

        return $this->createQueuedResponse($message);
    }

    public function createQueuedForScanResponse(
        string $playerName,
        ?PlayerScanProgress $scanProgress = null
    ): PlayerQueueResponse {
        $builder = PlayerQueueMessageBuilder::create()
            ->playerLink($playerName)
            ->text(' is currently being scanned.');

        if ($scanProgress !== null) {
            $this->appendScanProgressParts($builder, $scanProgress);
        }

        $builder->spinner();

        return $this->createQueuedResponse($builder->build());
    }

    public function createQueuePositionResponse(string $playerName, int|string $position): PlayerQueueResponse
    {
        $message = PlayerQueueMessageBuilder::create()
            ->playerLink($playerName)
            ->text(' is in the update queue, currently in position ' . (string) $position . '.')
            ->spinner()
            ->build();

        return $this->createQueuedResponse($message);
    }

    public function createQueueCompleteResponse(string $playerName): PlayerQueueResponse
    {
        $message = PlayerQueueMessageBuilder::create()
            ->playerLink($playerName)
            ->text(' has been updated!')
            ->build();

        return PlayerQueueResponse::complete($this->buildPlainText($message), $message);
    }

    public function createPlayerNotFoundResponse(string $playerName): PlayerQueueResponse
    {
        $message = PlayerQueueMessageBuilder::create()
            ->playerLink($playerName)
            ->text(' was not found. Please check the spelling and try again.')
            ->build();

        return PlayerQueueResponse::error($this->buildPlainText($message), $message);
    }

    /**
     * @param list<array<string, mixed>> $messageParts
     */
    private function createQueuedResponse(array $messageParts): PlayerQueueResponse
    {
        return PlayerQueueResponse::queued($this->buildPlainText($messageParts), $messageParts);
    }

  /**
   * @param list<array<string, mixed>> $messageParts
   */
    private function buildPlainText(array $messageParts): string
    {
        $builder = PlayerQueueMessageBuilder::create();

        foreach ($messageParts as $part) {
            $type = $part['type'] ?? '';

            if ($type === 'text') {
                $builder->text((string) ($part['value'] ?? ''));
            } elseif ($type === 'link') {
                $builder->link(
                    (string) ($part['href'] ?? ''),
                    (string) ($part['label'] ?? '')
                );
            } elseif ($type === 'emphasis') {
                $builder->emphasis((string) ($part['value'] ?? ''));
            } elseif ($type === 'progress') {
                $builder->progress(
                    (int) ($part['percentage'] ?? 0),
                    isset($part['title']) ? (string) $part['title'] : null,
                    isset($part['summary']) ? (string) $part['summary'] : null,
                );
            }
        }

        return $builder->toPlainText();
    }

    private function appendScanProgressParts(PlayerQueueMessageBuilder $builder, PlayerScanProgress $progress): void
    {
        $title = $progress->getTitle();
        $summary = $progress->getProgressSummary();
        $percentage = $progress->getPercentage();
        $formattedTitle = $title !== null ? $this->formatProgressTitle($title) : '';

        if ($formattedTitle !== '') {
            if (
                $title !== null
                && !$this->isErrorProgressTitle($title)
                && preg_match('/^(Updating|Fetching)\b/i', $title) !== 1
            ) {
                $normalizedTitle = preg_replace('/\s+for\s+[^.]+\.?$/i', '', trim($title)) ?? '';
                $normalizedTitle = rtrim($normalizedTitle, " .\t\n\r\0\v");
                $builder->text(' Working on ');
                $builder->emphasis($normalizedTitle);
            } else {
                $builder->text(' ' . $formattedTitle);
            }
        }

        if ($summary !== null) {
            $builder->text(' ' . $summary);
        }

        if ($formattedTitle !== '' || $summary !== null) {
            $builder->text('.');
        }

        if ($percentage !== null) {
            $builder->progress($percentage, $formattedTitle !== '' ? $formattedTitle : null, $summary);
        }
    }

    private function formatProgressTitle(string $title): string
    {
        $normalizedTitle = preg_replace('/\s+for\s+[^.]+\.?$/i', '', trim($title)) ?? '';
        $normalizedTitle = rtrim($normalizedTitle, " .\t\n\r\0\v");

        if ($normalizedTitle === '') {
            return '';
        }

        if (preg_match('/^(Updating|Fetching)\b/i', $normalizedTitle) === 1) {
            return ucfirst(strtolower($normalizedTitle));
        }

        if ($this->isErrorProgressTitle($normalizedTitle)) {
            return $normalizedTitle;
        }

        return 'Working on ' . $normalizedTitle;
    }

    private function isErrorProgressTitle(string $title): bool
    {
        return preg_match('/\\b(failed|error|problem|unable|denied|unavailable|timeout)\\b/i', $title) === 1;
    }

    private function createQueueLimitMessage(): string
    {
        return 'You have already entered ' . PlayerQueueService::MAX_QUEUE_SUBMISSIONS_PER_IP
            . ' players into the queue. Please wait a while.';
    }
}
