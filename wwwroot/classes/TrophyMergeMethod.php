<?php

declare(strict_types=1);

enum TrophyMergeMethod: string
{
    case Order = 'order';
    case Name = 'name';
    case Icon = 'icon';

    #[\NoDiscard]
    public static function tryFromMixed(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        return self::tryFrom(((string) $value) |> trim(...) |> strtolower(...));
    }

    /**
     * Parse a merge method from request input. Missing/empty values default to Order.
     *
     * @throws InvalidArgumentException when the value is present but not a known method
     */
    #[\NoDiscard]
    public static function fromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return self::Order;
        }

        return self::tryFromMixed($value)
            ?? throw new InvalidArgumentException('Wrong input');
    }

    public function progressLabel(): string
    {
        return match ($this) {
            self::Name => 'Matching trophies by name…',
            self::Icon => 'Matching trophies by icon…',
            self::Order => 'Matching trophies by list order…',
        };
    }
}
