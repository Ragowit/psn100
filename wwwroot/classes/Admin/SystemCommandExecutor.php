<?php

declare(strict_types=1);

final readonly class SystemCommandExecutor implements CommandExecutorInterface
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
        return $command
            |> (fn (array $parts): array => array_map(escapeshellarg(...), $parts))
            |> (fn (array $escapedParts): string => implode(' ', $escapedParts));
    }

    private function combineOutput(?string $stdout, ?string $stderr): string
    {
        $parts = [];

        if (is_string($stdout)) {
            $trimmedStdout = $stdout |> trim(...);
            if ($trimmedStdout !== '') {
                $parts[] = $trimmedStdout;
            }
        }

        if (is_string($stderr)) {
            $trimmedStderr = $stderr |> trim(...);
            if ($trimmedStderr !== '') {
                $parts[] = $trimmedStderr;
            }
        }

        return implode(PHP_EOL, $parts);
    }
}
