<?php

declare(strict_types=1);

final class CommandExecutionResult
{
    private int $exitCode;

    private string $output;

    public function __construct(int $exitCode, string $output)
    {
        $this->exitCode = $exitCode;
        $this->output = $output;
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
