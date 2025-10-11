<?php

declare(strict_types=1);

final class ExecutionEnvironmentConfigurator
{
    /**
     * @var array<string, string>
     */
    private array $iniSettings = [];

    private bool $unlimitedExecution = false;

    private bool $shouldIgnoreUserAbort = false;

    public static function create(): self
    {
        return new self();
    }

    public function addIniSetting(string $option, string $value): self
    {
        $this->iniSettings[$option] = $value;

        return $this;
    }

    public function enableUnlimitedExecution(): self
    {
        $this->unlimitedExecution = true;

        return $this;
    }

    public function enableIgnoreUserAbort(): self
    {
        $this->shouldIgnoreUserAbort = true;

        return $this;
    }

    public function configure(): void
    {
        $this->applyIniSettings();

        if ($this->unlimitedExecution) {
            $this->removeExecutionTimeLimit();
        }

        if ($this->shouldIgnoreUserAbort) {
            $this->configureIgnoreUserAbort();
        }
    }

    private function applyIniSettings(): void
    {
        if (!function_exists('ini_set')) {
            return;
        }

        foreach ($this->iniSettings as $option => $value) {
            @ini_set($option, $value);
        }
    }

    private function removeExecutionTimeLimit(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }

    private function configureIgnoreUserAbort(): void
    {
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
    }
}
