<?php

declare(strict_types=1);

enum PlayerGamesSort: string
{
    case Date = 'date';
    case InGameMaxRarity = 'max-in-game-rarity';
    case InGameRarity = 'in-game-rarity';
    case MaxRarity = 'max-rarity';
    case Name = 'name';
    case Rarity = 'rarity';
    case Search = 'search';

    #[\NoDiscard]
    public static function tryFromMixed(mixed $value): ?self
    {
        if (!is_string($value)) {
            return null;
        }

        return self::tryFrom($value |> trim(...) |> strtolower(...));
    }
}
