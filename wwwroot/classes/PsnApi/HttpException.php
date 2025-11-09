<?php

declare(strict_types=1);

namespace PsnApi;

class HttpException extends \RuntimeException
{
    private string $method;

    private string $uri;

    private int $statusCode;

    private string $body;

    /**
     * @var array<string, list<string>>
     */
    private array $headers;

    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        string $method,
        string $uri,
        int $statusCode,
        string $body,
        string $message = '',
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        $this->method = $method;
        $this->uri = $uri;
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $headers;

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

    /**
     * @return array<string, list<string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeaderLine(string $name): string
    {
        $normalizedName = strtolower($name);

        foreach ($this->headers as $headerName => $values) {
            if (strtolower($headerName) === $normalizedName) {
                return implode(', ', $values);
            }
        }

        return '';
    }
}
