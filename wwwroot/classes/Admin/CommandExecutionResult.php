<?php

declare(strict_types=1);

final readonly class CommandExecutionResult
{
    public function __construct(
        final private int $exitCode,
        final private string $output,
    ) {
    }

    #[\NoDiscard]
    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    #[\NoDiscard]
    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    #[\NoDiscard]
    public function getOutput(): string
    {
        return $this->output;
    }
}
