<?php

declare(strict_types=1);

final class PsnClientMode
{
    public const string LEGACY = 'legacy';
    public const string SHADOW = 'shadow';
    public const string NEW = 'new';

    private const string ENVIRONMENT_KEY = 'PSN_CLIENT_MODE';

    private static ?self $resolvedMode = null;

    private function __construct(private string $value)
    {
    }

    public static function current(): self
    {
        if (self::$resolvedMode !== null) {
            return self::$resolvedMode;
        }

        $configuration = self::loadAppConfiguration();
        $mode = $configuration['psn']['client_mode'] ?? self::LEGACY;

        self::$resolvedMode = self::fromValue($mode);

        return self::$resolvedMode;
    }

    public static function forService(string $service): self
    {
        $configuredOverrides = self::resolveConfiguredOverrides();
        $normalizedService = strtolower(trim($service));

        if ($normalizedService !== '' && isset($configuredOverrides[$normalizedService])) {
            return self::fromValue($configuredOverrides[$normalizedService]);
        }

        return self::current();
    }

    public static function fromValue(mixed $mode): self
    {
        if (!is_string($mode)) {
            throw new RuntimeException(
                sprintf(
                    'Invalid PSN client mode configuration. Expected one of [%s] in %s.',
                    implode(', ', self::allowedValues()),
                    self::ENVIRONMENT_KEY
                )
            );
        }

        $normalized = strtolower(trim($mode));

        if (!in_array($normalized, self::allowedValues(), true)) {
            throw new RuntimeException(
                sprintf(
                    'Invalid PSN client mode "%s". Allowed values: %s. Set %s accordingly.',
                    $mode,
                    implode(', ', self::allowedValues()),
                    self::ENVIRONMENT_KEY
                )
            );
        }

        return new self($normalized);
    }

    /**
     * @return list<string>
     */
    public static function allowedValues(): array
    {
        return [self::LEGACY, self::SHADOW, self::NEW];
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isLegacy(): bool
    {
        return $this->value === self::LEGACY;
    }

    public function isShadow(): bool
    {
        return $this->value === self::SHADOW;
    }

    public function isNew(): bool
    {
        return $this->value === self::NEW;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadAppConfiguration(): array
    {
        $configurationFile = __DIR__ . '/../config/app.php';

        if (!is_file($configurationFile)) {
            return [];
        }

        $configuration = @include $configurationFile;

        return is_array($configuration) ? $configuration : [];
    }

    /**
     * @return array<string, string>
     */
    private static function resolveConfiguredOverrides(): array
    {
        $configuration = self::loadAppConfiguration();
        $configuredOverrides = $configuration['psn']['client_mode_overrides'] ?? [];
        $environmentOverrides = self::readOverridesFromEnvironment();

        $combined = [];
        foreach ([$configuredOverrides, $environmentOverrides] as $overrideSource) {
            if (!is_array($overrideSource)) {
                continue;
            }

            foreach ($overrideSource as $service => $mode) {
                if (!is_string($service) || !is_string($mode)) {
                    continue;
                }

                $normalizedService = strtolower(trim($service));
                if ($normalizedService === '') {
                    continue;
                }

                $combined[$normalizedService] = $mode;
            }
        }

        return $combined;
    }

    /**
     * @return array<string, string>
     */
    private static function readOverridesFromEnvironment(): array
    {
        $rawValue = $_ENV['PSN_CLIENT_MODE_OVERRIDES_JSON'] ?? getenv('PSN_CLIENT_MODE_OVERRIDES_JSON');
        if (!is_string($rawValue) || trim($rawValue) === '') {
            return [];
        }

        $decoded = json_decode($rawValue, true);

        return is_array($decoded) ? $decoded : [];
    }
}
