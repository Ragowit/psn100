<?php

declare(strict_types=1);

enum GameTrophySort: string
{
    case Default = 'default';
    case Date = 'date';
    case Rarity = 'rarity';

    #[\NoDiscard]
    public static function fromMixed(mixed $value): self
    {
        if (!is_string($value)) {
            return self::Default;
        }

        return self::tryFrom($value |> trim(...) |> strtolower(...)) ?? self::Default;
    }
}
