<?php

declare(strict_types=1);

require_once __DIR__ . '/DeletePlayerConfirmation.php';

final class DeletePlayerRequestResult
{
    private ?string $successMessage;

    private ?string $errorMessage;

    private ?DeletePlayerConfirmation $confirmation;

    private function __construct(?string $successMessage, ?string $errorMessage, ?DeletePlayerConfirmation $confirmation)
    {
        $this->successMessage = $successMessage;
        $this->errorMessage = $errorMessage;
        $this->confirmation = $confirmation;
    }

    public static function empty(): self
    {
        return new self(null, null, null);
    }

    public static function success(string $message): self
    {
        return new self($message, null, null);
    }

    public static function error(string $message): self
    {
        return new self(null, $message, null);
    }

    public static function confirmation(DeletePlayerConfirmation $confirmation): self
    {
        return new self(null, null, $confirmation);
    }

    public function getSuccessMessage(): ?string
    {
        return $this->successMessage;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getConfirmation(): ?DeletePlayerConfirmation
    {
        return $this->confirmation;
    }
}
