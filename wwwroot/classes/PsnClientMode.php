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
}
