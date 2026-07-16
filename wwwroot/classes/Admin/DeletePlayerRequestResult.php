<?php

declare(strict_types=1);

require_once __DIR__ . '/DeletePlayerConfirmation.php';

final readonly class DeletePlayerRequestResult
{
    private function __construct(
        private ?string $successMessage,
        private ?string $errorMessage,
        private ?DeletePlayerConfirmation $confirmation
    ) {
    }

    #[\NoDiscard]
    public static function empty(): self
    {
        return new self(null, null, null);
    }

    #[\NoDiscard]
    public static function success(string $message): self
    {
        return new self($message, null, null);
    }

    #[\NoDiscard]
    public static function error(string $message): self
    {
        return new self(null, $message, null);
    }

    #[\NoDiscard]
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
