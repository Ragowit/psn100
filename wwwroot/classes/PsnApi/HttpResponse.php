<?php

declare(strict_types=1);

namespace PsnApi;

final class HttpResponse
{
    private int $statusCode;

    /**
     * @var array<string, list<string>>
     */
    private array $headers;

    private string $body;

    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(int $statusCode, array $headers, string $body)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
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

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return mixed
     */
    public function getJson()
    {
        $decoded = json_decode($this->body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to decode JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
