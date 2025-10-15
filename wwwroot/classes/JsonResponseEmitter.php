<?php

declare(strict_types=1);

final class JsonResponseEmitter
{
    /**
     * @param array<string, mixed>|\JsonSerializable $payload
     */
    public function respond(array|\JsonSerializable $payload, int $statusCode = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);

        try {
            echo json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->respondWithEncodingError();
        }
    }

    private function respondWithEncodingError(): void
    {
        http_response_code(500);

        $fallbackPayload = [
            'status' => 'error',
            'message' => 'An unexpected error occurred while encoding the response.',
            'shouldPoll' => false,
        ];

        $encodedFallback = json_encode($fallbackPayload);

        if ($encodedFallback === false) {
            echo '{"status":"error","message":"An unexpected error occurred while encoding the response.","shouldPoll":false}';

            return;
        }

        echo $encodedFallback;
    }
}
