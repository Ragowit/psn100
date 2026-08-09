<?php

declare(strict_types=1);

enum PlayerAdvisorSort: string
{
    case Difficulty = 'difficulty';
    case Rarity = 'rarity';

    #[\NoDiscard]
    public static function tryFromMixed(mixed $value): ?self
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = $value |> trim(...) |> strtolower(...);

        return match ($normalized) {
            'in_game_rarity', 'in-game-rarity' => self::Difficulty,
            default => self::tryFrom($normalized),
        };
    }
}
