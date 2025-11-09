<?php

declare(strict_types=1);

final class PsnPlayerSearchRateLimitException extends RuntimeException
{
    private DateTimeImmutable $retryAt;

    public function __construct(DateTimeImmutable $retryAt, string $message = '', ?Throwable $previous = null)
    {
        $this->retryAt = $retryAt;

        if ($message === '') {
            $message = sprintf(
                'PSN search rate limited until %s.',
                $retryAt->format(DateTimeInterface::RFC3339)
            );
        }

        parent::__construct($message, 0, $previous);
    }

    public function getRetryAt(): DateTimeImmutable
    {
        return $this->retryAt;
    }
}
