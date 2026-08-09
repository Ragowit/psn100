<?php

declare(strict_types=1);

enum GameListSort: string
{
    case Added = 'added';
    case Completion = 'completion';
    case Difficulty = 'difficulty';
    case Owners = 'owners';
    case Rarity = 'rarity';
    case Search = 'search';

    #[\NoDiscard]
    public static function tryFromMixed(mixed $value): ?self
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = $value |> trim(...) |> strtolower(...);

        return match ($normalized) {
            'in-game-rarity' => self::Difficulty,
            default => self::tryFrom($normalized),
        };
    }
}
