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
        foreach ($this->iniSettings as $option => $value) {
            @ini_set($option, $value);
        }
    }

    private function removeExecutionTimeLimit(): void
    {
        @set_time_limit(0);
    }

    private function configureIgnoreUserAbort(): void
    {
        @ignore_user_abort(true);
    }
}
