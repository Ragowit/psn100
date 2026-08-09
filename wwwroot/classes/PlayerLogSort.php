<?php

declare(strict_types=1);

enum PlayerLogSort: string
{
    case Date = 'date';
    case Difficulty = 'difficulty';
    case Rarity = 'rarity';

    #[\NoDiscard]
    public static function fromMixed(mixed $value): self
    {
        if (!is_string($value)) {
            return self::Date;
        }

        $normalized = $value |> trim(...) |> strtolower(...);

        return match ($normalized) {
            'in-game-rarity' => self::Difficulty,
            default => self::tryFrom($normalized) ?? self::Date,
        };
    }
}
