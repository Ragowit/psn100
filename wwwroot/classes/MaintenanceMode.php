<?php

declare(strict_types=1);

final class MaintenanceMode
{
    private bool $enabled;

    private string $templatePath;

    private function __construct(bool $enabled, string $templatePath)
    {
        $this->enabled = $enabled;
        $this->templatePath = $templatePath;
    }

    public static function disabled(string $templatePath): self
    {
        return new self(false, $templatePath);
    }

    public static function enabled(string $templatePath): self
    {
        return new self(true, $templatePath);
    }

    public static function fromFlag(bool $enabled, string $templatePath): self
    {
        return new self($enabled, $templatePath);
    }

    /**
     * @param array<string, mixed> $server
     */
    public static function fromEnvironment(array $server, string $templatePath, bool $default = false): self
    {
        $serverValue = $server['MAINTENANCE_MODE'] ?? getenv('MAINTENANCE_MODE');

        if ($serverValue === false || $serverValue === null) {
            return new self($default, $templatePath);
        }

        $normalized = strtolower(trim((string) $serverValue));

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return self::enabled($templatePath);
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return self::disabled($templatePath);
        }

        return new self($default, $templatePath);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }
}
