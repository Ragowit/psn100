<?php

declare(strict_types=1);

enum PlayerLogSort: string
{
    case Date = 'date';
    case Rarity = 'rarity';
    case InGameRarity = 'in-game-rarity';

    #[\NoDiscard]
    public static function fromMixed(mixed $value): self
    {
        if (!is_string($value)) {
            return self::Date;
        }

        return self::tryFrom($value |> trim(...) |> strtolower(...)) ?? self::Date;
    }
}
