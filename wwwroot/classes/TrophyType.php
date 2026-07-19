<?php

declare(strict_types=1);

enum TrophyType: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Platinum = 'platinum';

    #[\NoDiscard]
    public static function fromMixed(mixed $value): self
    {
        if (!is_string($value) && !is_numeric($value)) {
            return self::Bronze;
        }

        return self::tryFrom(((string) $value) |> trim(...) |> strtolower(...)) ?? self::Bronze;
    }

    public function color(): string
    {
        return match ($this) {
            self::Bronze => '#c46438',
            self::Silver => '#777777',
            self::Gold => '#c2903e',
            self::Platinum => '#667fb2',
        };
    }

    public function iconPath(): string
    {
        return '/img/trophy-' . $this->value . '.svg';
    }

    public function label(): string
    {
        return $this->value |> ucfirst(...);
    }

    /**
     * SQL FIELD() argument list for trophy type ordering.
     */
    public static function sqlFieldOrder(string $columnExpression): string
    {
        $quotedValues = array_map(
            static fn (self $type): string => "'" . $type->value . "'",
            self::cases()
        );

        return 'FIELD(' . $columnExpression . ', ' . implode(', ', $quotedValues) . ')';
    }
}
