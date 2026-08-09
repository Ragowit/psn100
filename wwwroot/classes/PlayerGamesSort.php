<?php

declare(strict_types=1);

enum PlayerGamesSort: string
{
    case Date = 'date';
    case Difficulty = 'difficulty';
    case MaxDifficulty = 'max-difficulty';
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

        $normalized = $value |> trim(...) |> strtolower(...);

        return match ($normalized) {
            'in-game-rarity' => self::Difficulty,
            'max-in-game-rarity' => self::MaxDifficulty,
            default => self::tryFrom($normalized),
        };
    }
}
