<?php

declare(strict_types=1);

require_once __DIR__ . '/Platform.php';
require_once __DIR__ . '/RequestParameter.php';

final readonly class HomepagePopularGamesFilter
{
    public const string PLATFORM_ALL = '';
    public const string PLATFORM_PC = Platform::Pc->value;
    public const string PLATFORM_PS3 = Platform::Ps3->value;
    public const string PLATFORM_PS4 = Platform::Ps4->value;
    public const string PLATFORM_PS5 = Platform::Ps5->value;
    public const string PLATFORM_PSVITA = Platform::PsVita->value;
    public const string PLATFORM_PSVR = Platform::PsVr->value;
    public const string PLATFORM_PSVR2 = Platform::PsVr2->value;

    private function __construct(
        final private string $platform,
        final private bool $exclusiveOnly,
    ) {
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    #[\NoDiscard]
    public static function fromArray(array $queryParameters): self
    {
        $platform = self::normalizePlatform($queryParameters['platform'] ?? null);
        $exclusiveOnly = RequestParameter::toBool($queryParameters['exclusive'] ?? null);

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
        return Platform::tryFrom($this->platform)?->label() ?? '';
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
        return [self::PLATFORM_ALL => 'All'] + Platform::labelsByValue();
    }

    private static function normalizePlatform(mixed $value): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return self::PLATFORM_ALL;
        }

        $platform = ((string) $value) |> trim(...) |> strtolower(...);

        return Platform::tryFrom($platform)?->value ?? self::PLATFORM_ALL;
    }

}
