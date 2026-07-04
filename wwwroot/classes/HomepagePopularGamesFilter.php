<?php

declare(strict_types=1);

final class HomepagePopularGamesFilter
{
    public const PLATFORM_ALL = '';
    public const PLATFORM_PC = 'pc';
    public const PLATFORM_PS3 = 'ps3';
    public const PLATFORM_PS4 = 'ps4';
    public const PLATFORM_PS5 = 'ps5';
    public const PLATFORM_PSVITA = 'psvita';
    public const PLATFORM_PSVR = 'psvr';
    public const PLATFORM_PSVR2 = 'psvr2';

    /**
     * @var list<string>
     */
    private const PLATFORM_KEYS = [
        self::PLATFORM_PC,
        self::PLATFORM_PS3,
        self::PLATFORM_PS4,
        self::PLATFORM_PS5,
        self::PLATFORM_PSVITA,
        self::PLATFORM_PSVR,
        self::PLATFORM_PSVR2,
    ];

    /**
     * @var array<string, string>
     */
    private const PLATFORM_LABELS = [
        self::PLATFORM_ALL => 'All',
        self::PLATFORM_PC => 'PC',
        self::PLATFORM_PS3 => 'PS3',
        self::PLATFORM_PS4 => 'PS4',
        self::PLATFORM_PS5 => 'PS5',
        self::PLATFORM_PSVITA => 'PSVITA',
        self::PLATFORM_PSVR => 'PSVR',
        self::PLATFORM_PSVR2 => 'PSVR2',
    ];

    private function __construct(
        private readonly string $platform,
        private readonly bool $exclusiveOnly,
    ) {
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function fromArray(array $queryParameters): self
    {
        $platform = self::normalizePlatform($queryParameters['platform'] ?? null);
        $exclusiveOnly = self::toBool($queryParameters['exclusive'] ?? null);

        return new self($platform, $exclusiveOnly);
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function hasPlatformFilter(): bool
    {
        return $this->platform !== self::PLATFORM_ALL;
    }

    public function isExclusiveOnly(): bool
    {
        return $this->exclusiveOnly;
    }

    public function isPlatformSelected(string $platform): bool
    {
        return $this->platform === $platform;
    }

    public function getPlatformDatabaseValue(): string
    {
        return self::PLATFORM_LABELS[$this->platform] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function getQueryParameters(): array
    {
        $parameters = [];

        if ($this->hasPlatformFilter()) {
            $parameters['platform'] = $this->platform;
        }

        if ($this->exclusiveOnly) {
            $parameters['exclusive'] = 'true';
        }

        return $parameters;
    }

    /**
     * @return array<string, string>
     */
    public static function getPlatformOptions(): array
    {
        return self::PLATFORM_LABELS;
    }

    private static function normalizePlatform(mixed $value): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return self::PLATFORM_ALL;
        }

        $platform = strtolower(trim((string) $value));

        if ($platform === '' || !in_array($platform, self::PLATFORM_KEYS, true)) {
            return self::PLATFORM_ALL;
        }

        return $platform;
    }

    private static function toBool(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            if ($value === '' || $value === 'false' || $value === '0') {
                return false;
            }

            return true;
        }

        return false;
    }
}
