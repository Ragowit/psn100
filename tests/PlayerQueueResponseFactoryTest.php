<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerQueueResponseFactory.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueResponse.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerScanProgress.php';

final class PlayerQueueResponseFactoryTest extends TestCase
{
    public function testCreateCheaterResponseIncludesStructuredMessageParts(): void
    {
        $factory = new PlayerQueueResponseFactory(new PlayerQueueService());

        $response = $factory->createCheaterResponse('Bad User', 'Account/123');

        $this->assertSame('error', $response->getStatus());
        $this->assertStringContainsString("Player 'Bad User' is tagged as a cheater", $response->getMessage());

        $parts = $response->getMessageParts();
        $this->assertTrue(is_array($parts));
        $this->assertSame('link', $parts[1]['type'] ?? null);
        $this->assertSame('/player/Bad%20User', $parts[1]['href'] ?? null);
        $this->assertSame('Bad User', $parts[1]['label'] ?? null);
        $this->assertSame('Dispute', $parts[3]['label'] ?? null);
    }

    public function testCreateQueuePositionResponseMarksQueuedWithStructuredParts(): void
    {
        $factory = new PlayerQueueResponseFactory(new PlayerQueueService());

        $response = $factory->createQueuePositionResponse('Queue <User>', '<script>');

        $this->assertSame('queued', $response->getStatus());
        $this->assertTrue($response->shouldPoll());
        $this->assertStringContainsString('currently in position <script>', $response->getMessage());

        $parts = $response->getMessageParts();
        $this->assertSame('link', $parts[0]['type'] ?? null);
        $this->assertSame('/player/Queue%20%3CUser%3E', $parts[0]['href'] ?? null);
        $this->assertSame('Queue <User>', $parts[0]['label'] ?? null);
        $this->assertSame('spinner', $parts[2]['type'] ?? null);
    }

    public function testCreateQueueLimitResponseReturnsErrorWithConfiguredLimitMessage(): void
    {
        $factory = new PlayerQueueResponseFactory(new PlayerQueueService());

        $response = $factory->createQueueLimitResponse();

        $this->assertSame('error', $response->getStatus());
        $this->assertSame(
            'You have already entered ' . PlayerQueueService::MAX_QUEUE_SUBMISSIONS_PER_IP
            . ' players into the queue. Please wait a while.',
            $response->getMessage()
        );
        $this->assertSame(null, $response->getMessageParts());
    }

    public function testCreateQueuedForAdditionResponseIncludesSpinnerPart(): void
    {
        $factory = new PlayerQueueResponseFactory(new PlayerQueueService());

        $response = $factory->createQueuedForAdditionResponse('Queue User');

        $this->assertSame('queued', $response->getStatus());
        $this->assertTrue($response->shouldPoll());

        $parts = $response->getMessageParts();
        $this->assertSame('Queue User', $parts[0]['label'] ?? null);
        $this->assertSame('spinner', $parts[2]['type'] ?? null);
    }

    public function testCreatePlayerNotFoundResponseReturnsStructuredLinkParts(): void
    {
        $factory = new PlayerQueueResponseFactory(new PlayerQueueService());

        $response = $factory->createPlayerNotFoundResponse('<Invalid>');

        $this->assertSame('error', $response->getStatus());
        $parts = $response->getMessageParts();
        $this->assertSame('/player/%3CInvalid%3E', $parts[0]['href'] ?? null);
        $this->assertSame('<Invalid>', $parts[0]['label'] ?? null);
    }

    public function testCreateQueueCompleteResponseReturnsCompleteStatusWithLinkPart(): void
    {
        $factory = new PlayerQueueResponseFactory(new PlayerQueueService());

        $response = $factory->createQueueCompleteResponse('Player One');

        $this->assertSame('complete', $response->getStatus());
        $this->assertFalse($response->shouldPoll());
        $parts = $response->getMessageParts();
        $this->assertSame('/player/Player%20One', $parts[0]['href'] ?? null);
        $this->assertSame('Player One', $parts[0]['label'] ?? null);
    }

    public function testCreateQueuedForScanResponseIncludesProgressPart(): void
    {
        $factory = new PlayerQueueResponseFactory(new PlayerQueueService());

        $progress = PlayerScanProgress::fromArray([
            'current' => 5,
            'total' => 34,
            'title' => 'Game <Title>',
        ]);
        $this->assertTrue($progress instanceof PlayerScanProgress, 'Expected PlayerScanProgress instance.');

        $response = $factory->createQueuedForScanResponse('Player <Name>', $progress);

        $this->assertSame('queued', $response->getStatus());
        $this->assertTrue($response->shouldPoll());
        $this->assertStringContainsString('Working on Game <Title> (5/34)', $response->getMessage());

        $parts = $response->getMessageParts();
        $emphasisParts = array_values(array_filter($parts ?? [], static fn (array $part): bool => ($part['type'] ?? '') === 'emphasis'));
        $progressParts = array_values(array_filter($parts ?? [], static fn (array $part): bool => ($part['type'] ?? '') === 'progress'));
        $this->assertSame('Game <Title>', $emphasisParts[0]['value'] ?? null);
        $this->assertSame(15, $progressParts[0]['percentage'] ?? null);
    }

    public function testCreateQueuedForScanResponseOmitsScanningPrefixForErrors(): void
    {
        $factory = new PlayerQueueResponseFactory(new PlayerQueueService());

        $progress = PlayerScanProgress::fromArray([
            'current' => null,
            'total' => null,
            'title' => 'Profile scan failed, waiting 1 minute before confirming privacy.',
        ]);
        $this->assertTrue($progress instanceof PlayerScanProgress, 'Expected PlayerScanProgress instance.');

        $response = $factory->createQueuedForScanResponse('dragonrider', $progress);

        $this->assertSame('queued', $response->getStatus());
        $this->assertStringContainsString('dragonrider is currently being scanned.', $response->getMessage());
        $this->assertStringContainsString('Profile scan failed, waiting 1 minute before confirming privacy.', $response->getMessage());
    }

    public function testCreateQueuedForScanResponseUsesActionVerbsForProgressTitle(): void
    {
        $factory = new PlayerQueueResponseFactory(new PlayerQueueService());

        $progress = PlayerScanProgress::fromArray([
            'current' => null,
            'total' => null,
            'title' => 'Updating avatar for Ragowit.',
        ]);
        $this->assertTrue($progress instanceof PlayerScanProgress, 'Expected PlayerScanProgress instance.');

        $response = $factory->createQueuedForScanResponse('Ragowit', $progress);

        $this->assertSame('queued', $response->getStatus());
        $this->assertStringContainsString('Updating avatar.', $response->getMessage());
    }

    public function testCreateBusyResponseUsesHttpStatus503(): void
    {
        $factory = new PlayerQueueResponseFactory(new PlayerQueueService());

        $response = $factory->createBusyResponse();

        $this->assertSame('error', $response->getStatus());
        $this->assertSame(503, $response->getHttpStatusCode());
    }
}
