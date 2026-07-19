<?php

declare(strict_types=1);

enum GameListSort: string
{
    case Added = 'added';
    case Completion = 'completion';
    case Owners = 'owners';
    case Rarity = 'rarity';
    case InGameRarity = 'in-game-rarity';
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
