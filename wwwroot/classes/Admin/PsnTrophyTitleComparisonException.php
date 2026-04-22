<?php

declare(strict_types=1);

final class PsnTrophyTitleComparisonException extends RuntimeException
{
    public function __construct(string $message, private readonly ?int $statusCode = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}
