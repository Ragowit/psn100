<?php

declare(strict_types=1);

enum TrophyRarityName: string
{
    case None = 'NONE';
    case Common = 'COMMON';
    case Uncommon = 'UNCOMMON';
    case Rare = 'RARE';
    case Epic = 'EPIC';
    case Legendary = 'LEGENDARY';

    #[\NoDiscard]
    public static function fromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (!is_string($value) && !is_numeric($value)) {
            return self::None;
        }

        return self::tryFrom(((string) $value) |> trim(...) |> strtoupper(...)) ?? self::None;
    }

    /**
     * Quote the enum value for safe embedding in SQL string literals.
     */
    public function toSqlLiteral(): string
    {
        return "'" . $this->value . "'";
    }
}
