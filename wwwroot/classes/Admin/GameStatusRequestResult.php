<?php

declare(strict_types=1);

class GameStatusRequestResult
{
    private ?string $successMessage;

    private ?string $errorMessage;

    private function __construct(?string $successMessage, ?string $errorMessage)
    {
        $this->successMessage = $successMessage;
        $this->errorMessage = $errorMessage;
    }

    public static function success(string $message): self
    {
        return new self($message, null);
    }

    public static function error(string $message): self
    {
        return new self(null, $message);
    }

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
