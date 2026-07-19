<?php

declare(strict_types=1);

enum PlayerAdvisorSort: string
{
    case Rarity = 'rarity';
    case InGameRarity = 'in_game_rarity';

    #[\NoDiscard]
    public static function tryFromMixed(mixed $value): ?self
    {
        if (!is_string($value)) {
            return null;
        }

        return self::tryFrom($value |> trim(...) |> strtolower(...));
    }
}
