<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyMergeProcessor.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyMergeRequestHandler.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyMergeProgressListener.php';

final class TrophyMergeProcessorTest extends TestCase
{
    private ?string $previousErrorLog = null;

    protected function setUp(): void
    {
        parent::setUp();
        http_response_code(200);
        $this->previousErrorLog = ini_get('error_log') ?: null;
        ini_set('error_log', sys_get_temp_dir() . '/trophy_merge_processor_test.log');
    }

    protected function tearDown(): void
    {
        if ($this->previousErrorLog === null) {
            ini_set('error_log', '');
        } else {
            ini_set('error_log', $this->previousErrorLog);
        }

        parent::tearDown();
    }

    public function testProcessRequestRejectsNonPostRequests(): void
    {
        $handler = new StubTrophyMergeRequestHandler();
        $processor = new TestableTrophyMergeProcessor($handler);

        $processor->processRequest([], ['REQUEST_METHOD' => 'GET']);

        $this->assertCount(1, $processor->jsonResponses);
        $this->assertSame(405, $processor->jsonResponses[0]['statusCode']);
        $this->assertSame([
            'success' => false,
            'error' => 'Method not allowed.',
        ], $processor->jsonResponses[0]['payload']);
        $this->assertCount(0, $processor->events);
        $this->assertCount(0, $handler->receivedPostData);
    }

    public function testProcessRequestValidatesNumericIds(): void
    {
        $handler = new StubTrophyMergeRequestHandler();
        $processor = new TestableTrophyMergeProcessor($handler);

        $processor->processRequest(['child' => 'abc', 'parent' => '123'], ['REQUEST_METHOD' => 'POST']);

        $this->assertCount(1, $processor->jsonResponses);
        $this->assertSame(400, $processor->jsonResponses[0]['statusCode']);
        $this->assertSame([
            'success' => false,
            'error' => 'Please provide numeric child and parent game ids.',
        ], $processor->jsonResponses[0]['payload']);
        $this->assertCount(0, $processor->events);
        $this->assertCount(0, $handler->receivedPostData);
    }

    public function testProcessRequestMergesGamesAndEmitsEvents(): void
    {
        $handler = new StubTrophyMergeRequestHandler();
        $processor = new TestableTrophyMergeProcessor($handler);

        $processor->processRequest(
            ['child' => '42', 'parent' => '24', 'method' => 'NAME'],
            ['REQUEST_METHOD' => 'POST']
        );

        $this->assertTrue($processor->streamPrepared);
        $this->assertCount(0, $processor->jsonResponses);
        $this->assertCount(1, $handler->receivedPostData);
        $this->assertSame([
            'child' => '42',
            'parent' => '24',
            'method' => 'name',
        ], $handler->receivedPostData[0]);

        $this->assertCount(4, $processor->events);
        $this->assertSame([
            'type' => 'progress',
            'progress' => 0,
            'message' => 'Preparing game merge…',
        ], $processor->events[0]);
        $this->assertSame([
            'type' => 'progress',
            'progress' => 25,
            'message' => 'First update',
        ], $processor->events[1]);
        $this->assertSame([
            'type' => 'progress',
            'progress' => 50,
            'message' => 'Second update',
        ], $processor->events[2]);
        $this->assertSame([
            'type' => 'complete',
            'success' => true,
            'progress' => 100,
            'message' => 'Merge complete.',
        ], $processor->events[3]);
    }

    public function testProcessRequestHandlesInvalidArgumentErrors(): void
    {
        $handler = new StubTrophyMergeRequestHandler();
        $handler->shouldThrowInvalidArgument = true;
        $processor = new TestableTrophyMergeProcessor($handler);

        $processor->processRequest(
            ['child' => '1', 'parent' => '2'],
            ['REQUEST_METHOD' => 'POST']
        );

        $this->assertTrue($processor->streamPrepared);
        $this->assertCount(0, $processor->jsonResponses);
        $this->assertCount(1, $handler->receivedPostData);
        $this->assertCount(2, $processor->events);
        $this->assertSame([
            'type' => 'progress',
            'progress' => 0,
            'message' => 'Preparing game merge…',
        ], $processor->events[0]);
        $this->assertSame([
            'type' => 'error',
            'success' => false,
            'progress' => 100,
            'error' => 'Invalid child or parent id.',
        ], $processor->events[1]);
    }

    public function testProcessRequestHandlesRuntimeErrors(): void
    {
        $handler = new StubTrophyMergeRequestHandler();
        $handler->shouldThrowRuntimeException = true;
        $processor = new TestableTrophyMergeProcessor($handler);

        $processor->processRequest(
            ['child' => '5', 'parent' => '6'],
            ['REQUEST_METHOD' => 'POST']
        );

        $this->assertTrue($processor->streamPrepared);
        $this->assertCount(0, $processor->jsonResponses);
        $this->assertCount(1, $handler->receivedPostData);
        $this->assertCount(2, $processor->events);
        $this->assertSame([
            'type' => 'progress',
            'progress' => 0,
            'message' => 'Preparing game merge…',
        ], $processor->events[0]);
        $this->assertSame([
            'type' => 'error',
            'success' => false,
            'progress' => 100,
            'error' => 'Merge failed.',
        ], $processor->events[1]);
    }

    public function testProcessRequestHandlesUnexpectedErrors(): void
    {
        $handler = new StubTrophyMergeRequestHandler();
        $handler->shouldThrowGenericException = true;
        $processor = new TestableTrophyMergeProcessor($handler);

        $processor->processRequest(
            ['child' => '3', 'parent' => '4'],
            ['REQUEST_METHOD' => 'POST']
        );

        $this->assertTrue($processor->streamPrepared);
        $this->assertCount(0, $processor->jsonResponses);
        $this->assertCount(1, $handler->receivedPostData);
        $this->assertCount(2, $processor->events);
        $this->assertSame([
            'type' => 'progress',
            'progress' => 0,
            'message' => 'Preparing game merge…',
        ], $processor->events[0]);
        $this->assertSame([
            'type' => 'error',
            'success' => false,
            'progress' => 100,
            'error' => 'An unexpected error occurred while merging the games.',
        ], $processor->events[1]);
    }
}

final class TestableTrophyMergeProcessor extends TrophyMergeProcessor
{
    /**
     * @var list<array{type:string, progress:int, message?:string, success?:bool, error?:string}>
     */
    public array $events = [];

    /**
     * @var list<array{statusCode:int, payload:array<string, mixed>}> 
     */
    public array $jsonResponses = [];

    public bool $streamPrepared = false;

    protected function prepareStreamResponse(): void
    {
        $this->streamPrepared = true;
    }

    protected function sendEvent(array $payload): void
    {
        $this->events[] = $payload;
    }

    protected function sendJsonResponse(int $statusCode, array $payload): void
    {
        $this->jsonResponses[] = [
            'statusCode' => $statusCode,
            'payload' => $payload,
        ];
        http_response_code($statusCode);
    }
}

final class StubTrophyMergeRequestHandler extends TrophyMergeRequestHandler
{
    /** @var list<array<string, string>> */
    public array $receivedPostData = [];

    public bool $shouldThrowInvalidArgument = false;

    public bool $shouldThrowRuntimeException = false;

    public bool $shouldThrowGenericException = false;

    public function __construct()
    {
        // Intentionally not calling the parent constructor. The test double overrides
        // the behavior that would otherwise require a TrophyMergeService instance.
    }

    public function handleGameMergeWithProgress(array $postData, TrophyMergeProgressListener $progressListener): string
    {
        $this->receivedPostData[] = $postData;

        if ($this->shouldThrowInvalidArgument) {
            throw new \InvalidArgumentException('Invalid child or parent id.');
        }

        if ($this->shouldThrowRuntimeException) {
            throw new \RuntimeException('Merge failed.');
        }

        if ($this->shouldThrowGenericException) {
            throw new \Exception('Unexpected failure.');
        }

        $progressListener->onProgress(25, 'First update');
        $progressListener->onProgress(50, 'Second update');

        return 'Merge complete.';
    }
}
