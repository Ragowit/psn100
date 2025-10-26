<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerQueueResponseFactory.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueResponse.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueService.php';

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
        $this->assertStringContainsString('https://github.com/Ragowit/psn100/issues?q=label%3Acheater+Bad%20User+OR+Account%2F123', $message);

        $this->assertSame(
            [
                'Bad User',
                '/player/Bad%20User',
                'https://github.com/Ragowit/psn100/issues?q=label%3Acheater+Bad%20User+OR+Account%2F123',
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
}
