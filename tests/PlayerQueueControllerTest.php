<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueController.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueHandler.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueRequest.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueResponse.php';

final class PlayerQueueHandlerSpy extends PlayerQueueHandler
{
    private PlayerQueueResponse $response;

    private ?PlayerQueueRequest $capturedRequest = null;

    private ?string $handledMethod = null;

    public function __construct(PlayerQueueResponse $response)
    {
        // Parent constructor is intentionally not called because the spy does not
        // need the real service dependencies. The parent defines private
        // properties so skipping the constructor is safe as they are unused.
        $this->response = $response;
    }

    public function getCapturedRequest(): ?PlayerQueueRequest
    {
        return $this->capturedRequest;
    }

    public function getHandledMethod(): ?string
    {
        return $this->handledMethod;
    }

    public function handleAddToQueueRequest(PlayerQueueRequest $request): PlayerQueueResponse
    {
        $this->capturedRequest = $request;
        $this->handledMethod = __FUNCTION__;

        return $this->response;
    }

    public function handleQueuePositionRequest(PlayerQueueRequest $request): PlayerQueueResponse
    {
        $this->capturedRequest = $request;
        $this->handledMethod = __FUNCTION__;

        return $this->response;
    }
}

final class PlayerQueueControllerTest extends TestCase
{
    public function testHandleAddToQueueBuildsRequestFromArrayData(): void
    {
        $response = PlayerQueueResponse::queued('queued response');
        $handler = new PlayerQueueHandlerSpy($response);
        $controller = new PlayerQueueController($handler);

        $requestData = ['q' => ['  ExampleUser  ', 'OtherValue']];
        $serverData = ['REMOTE_ADDR' => ['  192.0.2.10  ']];

        $result = $controller->handleAddToQueue($requestData, $serverData);

        $this->assertSame($response, $result);
        $this->assertSame('handleAddToQueueRequest', $handler->getHandledMethod());

        $capturedRequest = $handler->getCapturedRequest();
        $this->assertTrue($capturedRequest instanceof PlayerQueueRequest);
        $this->assertSame('ExampleUser', $capturedRequest->getPlayerName());
        $this->assertSame('192.0.2.10', $capturedRequest->getIpAddress());
    }

    public function testHandleQueuePositionBuildsRequestUsingSanitizedValues(): void
    {
        $response = PlayerQueueResponse::complete('complete response');
        $handler = new PlayerQueueHandlerSpy($response);
        $controller = new PlayerQueueController($handler);

        $requestData = ['q' => new class {
            public function __toString(): string
            {
                return 'QueueUser';
            }
        }];
        $serverData = ['REMOTE_ADDR' => new class {
            public function __toString(): string
            {
                return '198.51.100.23';
            }
        }];

        $result = $controller->handleQueuePosition($requestData, $serverData);

        $this->assertSame($response, $result);
        $this->assertSame('handleQueuePositionRequest', $handler->getHandledMethod());

        $capturedRequest = $handler->getCapturedRequest();
        $this->assertTrue($capturedRequest instanceof PlayerQueueRequest);
        $this->assertSame('QueueUser', $capturedRequest->getPlayerName());
        $this->assertSame('198.51.100.23', $capturedRequest->getIpAddress());
    }
}
