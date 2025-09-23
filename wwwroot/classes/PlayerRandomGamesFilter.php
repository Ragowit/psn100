<?php

declare(strict_types=1);

class PlayerRandomGamesFilter
{
    public const PLATFORM_PC = 'pc';
    public const PLATFORM_PS3 = 'ps3';
    public const PLATFORM_PS4 = 'ps4';
    public const PLATFORM_PS5 = 'ps5';
    public const PLATFORM_PSVITA = 'psvita';
    public const PLATFORM_PSVR = 'psvr';
    public const PLATFORM_PSVR2 = 'psvr2';

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
        foreach (self::getPlatformKeys() as $platformKey) {
            $selectedPlatforms[$platformKey] = self::toBool($parameters[$platformKey] ?? null);
        }

        return new self($selectedPlatforms);
    }

    /**
     * @return array<string>
     */
    private static function getPlatformKeys(): array
    {
        return [
            self::PLATFORM_PC,
            self::PLATFORM_PS3,
            self::PLATFORM_PS4,
            self::PLATFORM_PS5,
            self::PLATFORM_PSVITA,
            self::PLATFORM_PSVR,
            self::PLATFORM_PSVR2,
        ];
    }

    /**
     * @param mixed $value
     */
    private static function toBool($value): bool
    {
        return !empty($value);
    }

    public function isPlatformSelected(string $platform): bool
    {
        return $this->selectedPlatforms[$platform] ?? false;
    }
}
