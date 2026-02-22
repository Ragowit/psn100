<?php

declare(strict_types=1);

final readonly class PlayerRandomGamesFilter
{
    public const string PLATFORM_PC = 'pc';
    public const string PLATFORM_PS3 = 'ps3';
    public const string PLATFORM_PS4 = 'ps4';
    public const string PLATFORM_PS5 = 'ps5';
    public const string PLATFORM_PSVITA = 'psvita';
    public const string PLATFORM_PSVR = 'psvr';
    public const string PLATFORM_PSVR2 = 'psvr2';

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
     * @var array<string, bool>
     */
    private array $selectedPlatforms;

    private function __construct(array $selectedPlatforms)
    {
        $this->selectedPlatforms = $selectedPlatforms;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public static function fromArray(array $parameters): self
    {
        $selectedPlatforms = [];
        foreach (self::PLATFORM_KEYS as $platformKey) {
            $selectedPlatforms[$platformKey] = self::toBool($parameters[$platformKey] ?? null);
        }

        return new self($selectedPlatforms);
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

        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        $normalized = strtolower(trim((string) $value));

        return !in_array($normalized, ['', '0', 'false', 'off', 'no'], true);
    }

    public function isPlatformSelected(string $platform): bool
    {
        return $this->selectedPlatforms[$platform] ?? false;
    }
}
