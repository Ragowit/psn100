<?php

declare(strict_types=1);

final class SystemCommandExecutor implements CommandExecutorInterface
{
    /**
     * @param array<int, string> $command
     */
    #[\Override]
    public function run(array $command): CommandExecutionResult
    {
        if ($command === []) {
            return new CommandExecutionResult(1, 'No command provided.');
        }

        $commandString = $this->buildCommandString($command);
        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($commandString, $descriptorSpec, $pipes);

        if (!is_resource($process)) {
            return new CommandExecutionResult(1, 'Unable to start system command.');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $exitCode = proc_close($process);
        $output = $this->combineOutput($stdout, $stderr);

        return new CommandExecutionResult($exitCode, $output);
    }

    private function buildCommandString(array $command): string
    {
        $escapedParts = array_map(
            static fn(string $part): string => escapeshellarg($part),
            $command
        );

        return implode(' ', $escapedParts);
    }

    private function combineOutput(?string $stdout, ?string $stderr): string
    {
        $parts = [];

        if (is_string($stdout) && trim($stdout) !== '') {
            $parts[] = trim($stdout);
        }

        if (is_string($stderr) && trim($stderr) !== '') {
            $parts[] = trim($stderr);
        }

        return implode(PHP_EOL, $parts);
    }
}
