<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';

final class CronJobRunner
{
    private const string MINIMUM_MEMORY_LIMIT = '512M';
    private const int MINIMUM_MEMORY_LIMIT_BYTES = 536870912; // 512 MiB

    private bool $environmentConfigured = false;

    public static function create(): self
    {
        return new self();
    }

    public function run(CronJobInterface $job): void
    {
        $this->configureEnvironment();
        $job->run();
    }

    public function configureEnvironment(): void
    {
        $this->configureEnvironmentIfNeeded();
    }

    private function configureEnvironmentIfNeeded(): void
    {
        if ($this->environmentConfigured) {
            return;
        }

        $this->increaseMemoryLimitIfNeeded();
        $this->applyIniSetting('max_execution_time', '0');
        $this->applyIniSetting('mysql.connect_timeout', '0');

        @set_time_limit(0);

        $this->environmentConfigured = true;
    }

    private function applyIniSetting(string $option, string $value): void
    {
        @ini_set($option, $value);
    }

    private function increaseMemoryLimitIfNeeded(): void
    {
        $currentLimit = ini_get('memory_limit');

        if ($currentLimit === false || $currentLimit === '' || $currentLimit === '-1') {
            return;
        }

        $currentBytes = $this->parseIniSizeToBytes($currentLimit);

        if ($currentBytes !== null && $currentBytes >= self::MINIMUM_MEMORY_LIMIT_BYTES) {
            return;
        }

        $this->applyIniSetting('memory_limit', self::MINIMUM_MEMORY_LIMIT);
    }

    private function parseIniSizeToBytes(string $value): ?int
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (is_numeric($trimmed)) {
            return (int) $trimmed;
        }

        if (str_ends_with($trimmed, 'B') || str_ends_with($trimmed, 'b')) {
            $trimmed = substr($trimmed, 0, -1);
            $trimmed = trim($trimmed);
        }

        if ($trimmed === '') {
            return null;
        }

        $unit = strtolower(substr($trimmed, -1));
        $numberPart = substr($trimmed, 0, -1);

        if ($numberPart === '' || !is_numeric($numberPart)) {
            return null;
        }

        $number = (float) $numberPart;

        switch ($unit) {
            case 'g':
                $number *= 1024;
                // no break intentionally
            case 'm':
                $number *= 1024;
                // no break intentionally
            case 'k':
                $number *= 1024;
                return (int) round($number);
        }

        return null;
    }
}
