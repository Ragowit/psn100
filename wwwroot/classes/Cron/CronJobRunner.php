<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';

final class CronJobRunner
{
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

        $this->applyIniSetting('max_execution_time', '0');
        $this->applyIniSetting('mysql.connect_timeout', '0');

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $this->environmentConfigured = true;
    }

    private function applyIniSetting(string $option, string $value): void
    {
        if (function_exists('ini_set')) {
            @ini_set($option, $value);
        }
    }
}
