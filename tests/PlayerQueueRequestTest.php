<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerQueueRequest.php';

final class PlayerQueueRequestTest extends TestCase
{
    public function testFromArraysTrimsAndNormalizesValues(): void
    {
        $request = PlayerQueueRequest::fromArrays(
            ['q' => '  ExamplePlayer  '],
            ['REMOTE_ADDR' => '  127.0.0.1  ']
        );

        $this->assertSame('ExamplePlayer', $request->getPlayerName());
        $this->assertSame('127.0.0.1', $request->getIpAddress());
        $this->assertFalse($request->isPlayerNameEmpty());
    }

    public function testFromArraysUsesFirstArrayValueAndTrims(): void
    {
        $request = PlayerQueueRequest::fromArrays(
            ['q' => [' first ', ' second ']],
            ['REMOTE_ADDR' => [' 8.8.8.8 ', ' 9.9.9.9 ']]
        );

        $this->assertSame('first', $request->getPlayerName());
        $this->assertSame('8.8.8.8', $request->getIpAddress());
    }

    public function testFromArraysHandlesNonScalarAndStringableValues(): void
    {
        $stringable = new class {
            public function __toString(): string
            {
                return 'Player <name>';
            }
        };

        $resource = fopen('php://temp', 'r');

        $request = PlayerQueueRequest::fromArrays(
            ['q' => $stringable],
            ['REMOTE_ADDR' => $resource]
        );

        if (is_resource($resource)) {
            fclose($resource);
        }

        $this->assertSame('Player <name>', $request->getPlayerName());
        $this->assertSame('', $request->getIpAddress());
        $this->assertFalse($request->isPlayerNameEmpty());

        $emptyRequest = PlayerQueueRequest::fromArrays(
            ['q' => new stdClass()],
            ['REMOTE_ADDR' => null]
        );

        $this->assertSame('', $emptyRequest->getPlayerName());
        $this->assertSame('', $emptyRequest->getIpAddress());
        $this->assertTrue($emptyRequest->isPlayerNameEmpty());
    }
}
