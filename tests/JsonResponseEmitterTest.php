<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/JsonResponseEmitter.php';

final class JsonResponseEmitterTest extends TestCase
{
    protected function setUp(): void
    {
        http_response_code(200);
    }

    public function testRespondWithArrayPayloadOutputsJsonAndSetsStatusCode(): void
    {
        $emitter = new JsonResponseEmitter();
        $payload = [
            'status' => 'ok',
            'count' => 5,
        ];

        ob_start();
        $emitter->respond($payload, 202);
        $output = ob_get_clean();

        $this->assertSame(
            json_encode($payload, JSON_THROW_ON_ERROR),
            $output
        );
        $this->assertSame(202, http_response_code());
    }

    public function testRespondWithInvalidPayloadFallsBackToErrorResponse(): void
    {
        $emitter = new JsonResponseEmitter();
        $payload = [
            'invalid' => "\xB1",
        ];

        ob_start();
        $emitter->respond($payload);
        $output = ob_get_clean();

        $this->assertSame(500, http_response_code());
        $this->assertSame(
            json_encode(
                [
                    'status' => 'error',
                    'message' => 'An unexpected error occurred while encoding the response.',
                    'shouldPoll' => false,
                ]
            ),
            $output
        );
    }
}
