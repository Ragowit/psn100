<?php

declare(strict_types=1);

/**
 * Sentinel and helpers for player country codes stored on the player table.
 *
 * Unknown/unset countries are persisted as "zz" (ISO 3166 user-assigned code).
 */
enum PlayerCountryCode: string
{
    case Unknown = 'zz';

    #[\NoDiscard]
    public static function normalize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = $value |> trim(...) |> strtolower(...);

        return $normalized === '' ? null : $normalized;
    }

    #[\NoDiscard]
    public static function isUnknown(mixed $value): bool
    {
        $normalized = self::normalize($value);

        return $normalized === null || $normalized === self::Unknown->value;
    }

    /**
     * Resolve a country value to a stored code, falling back to Unknown.
     */
    #[\NoDiscard]
    public static function orUnknown(?string $value): string
    {
        return self::normalize($value) ?? self::Unknown->value;
    }
}
