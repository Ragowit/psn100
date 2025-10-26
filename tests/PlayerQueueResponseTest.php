<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerQueueResponse.php';

final class PlayerQueueResponseTest extends TestCase
{
    public function testQueuedResponseIncludesShouldPollTrue(): void
    {
        $response = PlayerQueueResponse::queued('Queued for processing');

        $this->assertSame('queued', $response->getStatus());
        $this->assertSame('Queued for processing', $response->getMessage());
        $this->assertTrue($response->shouldPoll());
        $this->assertSame(
            [
                'status' => 'queued',
                'message' => 'Queued for processing',
                'shouldPoll' => true,
            ],
            $response->toArray()
        );
        $this->assertSame($response->toArray(), $response->jsonSerialize());
    }

    public function testCompleteResponseIncludesShouldPollFalse(): void
    {
        $response = PlayerQueueResponse::complete('Processing complete');

        $this->assertSame('complete', $response->getStatus());
        $this->assertSame('Processing complete', $response->getMessage());
        $this->assertFalse($response->shouldPoll());
        $this->assertSame(
            [
                'status' => 'complete',
                'message' => 'Processing complete',
                'shouldPoll' => false,
            ],
            $response->toArray()
        );
        $this->assertSame($response->toArray(), $response->jsonSerialize());
    }

    public function testErrorResponseIncludesShouldPollFalse(): void
    {
        $response = PlayerQueueResponse::error('An error occurred');

        $this->assertSame('error', $response->getStatus());
        $this->assertSame('An error occurred', $response->getMessage());
        $this->assertFalse($response->shouldPoll());
        $this->assertSame(
            [
                'status' => 'error',
                'message' => 'An error occurred',
                'shouldPoll' => false,
            ],
            $response->toArray()
        );
        $this->assertSame($response->toArray(), $response->jsonSerialize());
    }
}
