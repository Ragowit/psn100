<?php

declare(strict_types=1);

namespace PsnApi;

class HttpException extends \RuntimeException
{
    private int $statusCode;

    private string $body;

    public function __construct(int $statusCode, string $body, string $message = '')
    {
        $this->statusCode = $statusCode;
        $this->body = $body;

        if ($message === '') {
            $message = 'HTTP request returned status code ' . $statusCode . '.';
        }

        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
