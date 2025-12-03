<?php

declare(strict_types=1);

readonly class PlayerReportResult
{
    private function __construct(private bool $hasMessage, private bool $success, private string $message)
    {
    }

    public static function success(string $message): self
    {
        return new self(true, true, $message);
    }

    public static function error(string $message): self
    {
        return new self(true, false, $message);
    }

    public static function empty(): self
    {
        return new self(false, false, '');
    }

    public function hasMessage(): bool
    {
        return $this->hasMessage;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
