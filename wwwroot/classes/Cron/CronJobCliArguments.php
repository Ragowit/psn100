<?php

declare(strict_types=1);

final class CronJobCliArguments
{
    private array $arguments;

    private function __construct(array $arguments)
    {
        $this->arguments = $arguments;
    }

    public static function fromArgv(array $argv): self
    {
        $arguments = [];

        if (count($argv) > 1) {
            parse_str(implode('&', array_slice($argv, 1)), $arguments);

            if (!is_array($arguments)) {
                $arguments = [];
            }
        }

        return new self($arguments);
    }

    public function getWorkerId(): int
    {
        return $this->getIntArgument('worker');
    }

    private function getIntArgument(string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $this->arguments)) {
            return $default;
        }

        $value = $this->arguments[$key];

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $filteredValue = filter_var($value, FILTER_VALIDATE_INT);
            if ($filteredValue !== false && $filteredValue !== null) {
                return (int) $filteredValue;
            }
        }

        return $default;
    }
}
