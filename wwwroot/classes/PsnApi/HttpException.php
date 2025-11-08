<?php

declare(strict_types=1);

namespace PsnApi;

class HttpException extends \RuntimeException
{
    private string $method;

    private string $uri;

    private int $statusCode;

    private string $body;

    public function __construct(
        string $method,
        string $uri,
        int $statusCode,
        string $body,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        $this->method = $method;
        $this->uri = $uri;
        $this->statusCode = $statusCode;
        $this->body = $body;

        if ($message === '') {
            $message = sprintf(
                'HTTP %s %s returned status code %d.',
                strtoupper($method),
                $uri,
                $statusCode
            );
        }

        parent::__construct($message, $statusCode, $previous);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
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
