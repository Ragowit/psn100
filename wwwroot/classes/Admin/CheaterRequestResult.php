<?php

declare(strict_types=1);

final readonly class CheaterRequestResult
{
    private function __construct(
        final private ?string $successMessage,
        final private ?string $errorMessage,
    ) {
    }

    #[\NoDiscard]
    public static function success(string $message): self
    {
        return new self($message, null);
    }

    #[\NoDiscard]
    public static function error(string $message): self
    {
        return new self(null, $message);
    }

    #[\NoDiscard]
    public static function empty(): self
    {
        return new self(null, null);
    }

    public function getSuccessMessage(): ?string
    {
        return $this->successMessage;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
