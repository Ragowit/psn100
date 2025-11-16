<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerQueueResponseFactory.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueResponse.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerScanProgress.php';

/**
 * @extends PlayerQueueService
 */
final class RecordingPlayerQueueServiceStub extends PlayerQueueService
{
    /** @var list<string> */
    private array $escapedValues = [];

    public function __construct()
    {
        // Parent constructor requires a PDO instance which is not needed for tests.
    }

    public function escapeHtml(string $value): string
    {
        $this->escapedValues[] = $value;

        return htmlentities($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @return list<string>
     */
    public function getEscapedValues(): array
    {
        return $this->escapedValues;
    }
}

final class PlayerQueueResponseFactoryTest extends TestCase
{
    public function testCreateCheaterResponseEscapesValuesAndIncludesDisputeLink(): void
    {
        $service = new RecordingPlayerQueueServiceStub();
        $factory = new PlayerQueueResponseFactory($service);

        $response = $factory->createCheaterResponse('Bad User', 'Account/123');

        $this->assertSame('error', $response->getStatus());

        $message = $response->getMessage();
        $this->assertStringContainsString("Player '<a class=\"link-underline link-underline-opacity-0 link-underline-opacity-100-hover\" href=\"/player/Bad%20User\">Bad User</a>' is tagged as a cheater and won't be scanned.", $message);
        $this->assertStringContainsString('Dispute</a>?', $message);
        $this->assertStringContainsString('https://github.com/Ragowit/psn100/issues?q=label%3Acheater%20Bad%20User%20OR%20Account%2F123', $message);

        $this->assertSame(
            [
                'Bad User',
                '/player/Bad%20User',
                'https://github.com/Ragowit/psn100/issues?q=label%3Acheater%20Bad%20User%20OR%20Account%2F123',
            ],
            $service->getEscapedValues()
        );
    }

    public function testCreateQueuePositionResponseMarksQueuedAndEscapesValues(): void
    {
        $service = new RecordingPlayerQueueServiceStub();
        $factory = new PlayerQueueResponseFactory($service);

        $response = $factory->createQueuePositionResponse('Queue <User>', '<script>');

        $this->assertSame('queued', $response->getStatus());
        $this->assertTrue($response->shouldPoll());

        $message = $response->getMessage();
        $this->assertStringContainsString('href="/player/Queue%20%3CUser%3E"', $message);
        $this->assertStringContainsString('">Queue &lt;User&gt;</a> is in the update queue, currently in position &lt;script&gt;.', $message);
        $this->assertStringContainsString('<div class="spinner-border" role="status">', $message);

        $this->assertSame(
            ['<script>', 'Queue <User>', '/player/Queue%20%3CUser%3E'],
            $service->getEscapedValues()
        );
    }

    public function testCreateQueueLimitResponseReturnsErrorWithConfiguredLimitMessage(): void
    {
        $service = new RecordingPlayerQueueServiceStub();
        $factory = new PlayerQueueResponseFactory($service);

        $response = $factory->createQueueLimitResponse();

        $this->assertSame('error', $response->getStatus());
        $this->assertSame(
            'You have already entered ' . PlayerQueueService::MAX_QUEUE_SUBMISSIONS_PER_IP
            . ' players into the queue. Please wait a while.',
            $response->getMessage()
        );
        $this->assertSame([], $service->getEscapedValues());
    }

    public function testCreateQueuedForAdditionResponseEscapesPlayerAndAddsSpinner(): void
    {
        $service = new RecordingPlayerQueueServiceStub();
        $factory = new PlayerQueueResponseFactory($service);

        $response = $factory->createQueuedForAdditionResponse('Queue User');

        $this->assertSame('queued', $response->getStatus());
        $this->assertTrue($response->shouldPoll());

        $message = $response->getMessage();
        $this->assertStringContainsString('href="/player/Queue%20User"', $message);
        $this->assertStringContainsString('">Queue User</a> is being added to the queue.', $message);
        $this->assertStringContainsString('<div class="spinner-border" role="status">', $message);

        $this->assertSame(
            ['Queue User', '/player/Queue%20User'],
            $service->getEscapedValues()
        );
    }

    public function testCreatePlayerNotFoundResponseReturnsErrorWithEscapedLink(): void
    {
        $service = new RecordingPlayerQueueServiceStub();
        $factory = new PlayerQueueResponseFactory($service);

        $response = $factory->createPlayerNotFoundResponse('<Invalid>');

        $this->assertSame('error', $response->getStatus());

        $message = $response->getMessage();
        $this->assertStringContainsString('href="/player/%3CInvalid%3E"', $message);
        $this->assertStringContainsString('">&lt;Invalid&gt;</a> was not found. Please check the spelling and try again.', $message);

        $this->assertSame(
            ['<Invalid>', '/player/%3CInvalid%3E'],
            $service->getEscapedValues()
        );
    }

    public function testCreateQueueCompleteResponseReturnsCompleteStatusWithEscapedLink(): void
    {
        $service = new RecordingPlayerQueueServiceStub();
        $factory = new PlayerQueueResponseFactory($service);

        $response = $factory->createQueueCompleteResponse('Player One');

        $this->assertSame('complete', $response->getStatus());
        $this->assertFalse($response->shouldPoll());

        $message = $response->getMessage();
        $this->assertStringContainsString('href="/player/Player%20One"', $message);
        $this->assertStringContainsString('">Player One</a> has been updated!', $message);

        $this->assertSame(
            ['Player One', '/player/Player%20One'],
            $service->getEscapedValues()
        );
    }

    public function testCreateQueuedForScanResponseIncludesProgressDetails(): void
    {
        $service = new RecordingPlayerQueueServiceStub();
        $factory = new PlayerQueueResponseFactory($service);

        $progress = PlayerScanProgress::fromArray([
            'current' => 5,
            'total' => 34,
            'title' => 'Game <Title>',
        ]);
        $this->assertTrue($progress instanceof PlayerScanProgress, 'Expected PlayerScanProgress instance.');

        $response = $factory->createQueuedForScanResponse('Player <Name>', $progress);

        $this->assertSame('queued', $response->getStatus());
        $this->assertTrue($response->shouldPoll());

        $message = $response->getMessage();
        $this->assertStringContainsString('href="/player/Player%20%3CName%3E"', $message);
        $this->assertStringContainsString('Currently scanning <strong>Game &lt;Title&gt;</strong> (5/34).', $message);
        $this->assertStringContainsString('class="progress mt-2"', $message);
        $this->assertStringContainsString('spinner-border', $message);

        $this->assertSame(
            ['Player <Name>', '/player/Player%20%3CName%3E', 'Game <Title>'],
            $service->getEscapedValues()
        );
    }
}
