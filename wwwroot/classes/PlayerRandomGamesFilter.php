<?php

declare(strict_types=1);

require_once __DIR__ . '/Platform.php';

final readonly class PlayerRandomGamesFilter
{
    public const string PLATFORM_PC = Platform::Pc->value;
    public const string PLATFORM_PS3 = Platform::Ps3->value;
    public const string PLATFORM_PS4 = Platform::Ps4->value;
    public const string PLATFORM_PS5 = Platform::Ps5->value;
    public const string PLATFORM_PSVITA = Platform::PsVita->value;
    public const string PLATFORM_PSVR = Platform::PsVr->value;
    public const string PLATFORM_PSVR2 = Platform::PsVr2->value;

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
    #[\NoDiscard]
    public static function fromArray(array $parameters): self
    {
        $selectedPlatforms = [];
        foreach (Platform::values() as $platformKey) {
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

        $normalized = ((string) $value) |> trim(...) |> strtolower(...);

        return !in_array($normalized, ['', '0', 'false', 'off', 'no'], true);
    }

    public function isPlatformSelected(string $platform): bool
    {
        return $this->selectedPlatforms[$platform] ?? false;
    }
}
