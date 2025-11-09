<?php

declare(strict_types=1);

final class PsnPlayerSearchRateLimitException extends RuntimeException
{
    private ?DateTimeImmutable $retryAt;

    public function __construct(?DateTimeImmutable $retryAt, ?Throwable $previous = null)
    {
        $this->retryAt = $retryAt;

        $message = 'The PlayStation Network rate limited the player search request.';

        if ($retryAt !== null) {
            $message .= sprintf(' Retry available at %s.', $retryAt->format(DateTimeInterface::RFC3339));
        }

        parent::__construct($message, 0, $previous);
    }

    public function getRetryAt(): ?DateTimeImmutable
    {
        return $this->retryAt;
    }
}
