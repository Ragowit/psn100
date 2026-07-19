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
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function labelsByValue(): array
    {
        $labels = [];

        foreach (self::cases() as $platform) {
            $labels[$platform->value] = $platform->label();
        }

        return $labels;
    }
}
