<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueEndpoint.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueController.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueResponse.php';
require_once __DIR__ . '/../wwwroot/classes/JsonResponseEmitter.php';

final class PlayerQueueControllerSpy extends PlayerQueueController
{
    private PlayerQueueResponse $response;

    private ?string $handledMethod = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $capturedRequestData = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $capturedServerData = null;

    public function __construct(PlayerQueueResponse $response)
    {
        $this->response = $response;
    }

    public function getHandledMethod(): ?string
    {
        return $this->handledMethod;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCapturedRequestData(): ?array
    {
        return $this->capturedRequestData;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCapturedServerData(): ?array
    {
        return $this->capturedServerData;
    }

    public function handleAddToQueue(array $requestData, array $serverData): PlayerQueueResponse
    {
        $this->handledMethod = __FUNCTION__;
        $this->capturedRequestData = $requestData;
        $this->capturedServerData = $serverData;

        return $this->response;
    }

    public function handleQueuePosition(array $requestData, array $serverData): PlayerQueueResponse
    {
        $this->handledMethod = __FUNCTION__;
        $this->capturedRequestData = $requestData;
        $this->capturedServerData = $serverData;

        return $this->response;
    }
}

final class PlayerQueueEndpointTest extends TestCase
{
    public function testHandleAddToQueueDelegatesToControllerAndEmitsJsonResponse(): void
    {
        $response = PlayerQueueResponse::queued('queued response');
        $controller = new PlayerQueueControllerSpy($response);
        $endpoint = PlayerQueueEndpoint::create($controller, new JsonResponseEmitter());

        $requestData = ['player' => 'ExampleUser'];
        $serverData = ['REMOTE_ADDR' => '192.0.2.1'];

        header_remove();
        ob_start();

        $endpoint->handleAddToQueue($requestData, $serverData);

        $output = ob_get_clean();

        $this->assertSame('handleAddToQueue', $controller->getHandledMethod());
        $this->assertSame($requestData, $controller->getCapturedRequestData());
        $this->assertSame($serverData, $controller->getCapturedServerData());

        $decodedOutput = json_decode((string) $output, true);
        $this->assertTrue(is_array($decodedOutput));
        $this->assertSame($response->toArray(), $decodedOutput);
        $this->assertSame(200, http_response_code());
    }

    public function testHandleQueuePositionDelegatesToControllerAndEmitsJsonResponse(): void
    {
        $response = PlayerQueueResponse::complete('complete response');
        $controller = new PlayerQueueControllerSpy($response);
        $endpoint = PlayerQueueEndpoint::create($controller, new JsonResponseEmitter());

        $requestData = ['player' => 'QueueUser'];
        $serverData = ['REMOTE_ADDR' => '198.51.100.23'];

        header_remove();
        ob_start();

        $endpoint->handleQueuePosition($requestData, $serverData);

        $output = ob_get_clean();

        $this->assertSame('handleQueuePosition', $controller->getHandledMethod());
        $this->assertSame($requestData, $controller->getCapturedRequestData());
        $this->assertSame($serverData, $controller->getCapturedServerData());

        $decodedOutput = json_decode((string) $output, true);
        $this->assertTrue(is_array($decodedOutput));
        $this->assertSame($response->toArray(), $decodedOutput);
        $this->assertSame(200, http_response_code());
    }
}
