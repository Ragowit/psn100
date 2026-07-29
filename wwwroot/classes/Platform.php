<?php

declare(strict_types=1);

enum Platform: string
{
    case Pc = 'pc';
    case Ps3 = 'ps3';
    case Ps4 = 'ps4';
    case Ps5 = 'ps5';
    case PsVita = 'psvita';
    case PsVr = 'psvr';
    case PsVr2 = 'psvr2';

    #[\NoDiscard]
    public function label(): string
    {
        return match ($this) {
            self::Pc => 'PC',
            self::Ps3 => 'PS3',
            self::Ps4 => 'PS4',
            self::Ps5 => 'PS5',
            self::PsVita => 'PSVITA',
            self::PsVr => 'PSVR',
            self::PsVr2 => 'PSVR2',
        };
    }

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Platforms that still prefer the legacy PSN trophy service name.
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public static function legacyTrophyServiceLabels(): array
    {
        return [
            self::Ps3->label(),
            self::Ps4->label(),
            self::PsVr->label(),
            self::PsVita->label(),
        ];
    }

    /**
     * Display / merge sort order for platform labels (e.g. "PS5", "PC").
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public static function labelOrder(): array
    {
        return [
            self::Ps3->label(),
            self::PsVita->label(),
            self::Ps4->label(),
            self::PsVr->label(),
            self::Ps5->label(),
            self::PsVr2->label(),
            self::Pc->label(),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function labelsByValue(): array
    {
        return self::cases()
            |> (fn (array $platforms): array => array_combine(
                array_column($platforms, 'value'),
                array_map(static fn (self $platform): string => $platform->label(), $platforms),
            ));
    }

    /**
     * Whether a platform field (comma-separated labels or a single label)
     * should use PlayStation 5 artwork fallbacks.
     */
    #[\NoDiscard]
    public static function usesPlayStation5Assets(string $platforms): bool
    {
        return array_any(
            [self::Ps5->label(), self::PsVr2->label()],
            static fn (string $label): bool => str_contains($platforms, $label),
        );
    }

    /**
     * @param list<string> $platforms
     */
    #[\NoDiscard]
    public static function anyUsesPlayStation5Assets(array $platforms): bool
    {
        return array_any(
            $platforms,
            static fn (string $platform): bool => self::usesPlayStation5Assets($platform),
        );
    }
}
