<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Exceptions;

final class ApiException extends \RuntimeException
{
    private ?array $responseBody;

    public function __construct(string $message, int $code, ?array $responseBody = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->responseBody = $responseBody;
    }

    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }
}
