<?php

declare(strict_types=1);

final readonly class CommandExecutionResult
{
    public function __construct(
        private int $exitCode,
        private string $output
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getOutput(): string
    {
        return $this->output;
    }
}
