<?php

declare(strict_types=1);

final class CommaSeparatedValues
{
    /**
     * @return list<string>
     */
    public static function parseTrimmed(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return $value
            |> (fn(string $raw): array => explode(',', $raw))
            |> (fn(array $parts): array => array_map(trim(...), $parts))
            |> (fn(array $parts): array => array_filter($parts, static fn(string $part): bool => $part !== ''))
            |> array_values(...);
    }

    /**
     * @return list<string>
     */
    public static function parseUppercaseTrimmed(string $value): array
    {
        return self::parseTrimmed($value)
            |> (fn(array $parts): array => array_map(strtoupper(...), $parts));
    }
}
