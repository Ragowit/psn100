<?php

declare(strict_types=1);

enum PsnTrophyTitleComparisonSource: string
{
    case Direct = 'direct';
    case Tustin = 'tustin';

    #[\NoDiscard]
    public static function fromMixed(mixed $value): self
    {
        if (!is_string($value) && !is_numeric($value)) {
            return self::Direct;
        }

        return self::tryFrom(((string) $value) |> trim(...) |> strtolower(...)) ?? self::Direct;
    }

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct endpoint',
            self::Tustin => 'tustin/psn-php',
        };
    }
}
