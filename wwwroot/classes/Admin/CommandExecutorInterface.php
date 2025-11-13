<?php

declare(strict_types=1);

interface CommandExecutorInterface
{
    /**
     * @param array<int, string> $command
     */
    public function run(array $command): CommandExecutionResult;
}
