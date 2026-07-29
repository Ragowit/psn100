<?php

declare(strict_types=1);

final readonly class RequestParameter
{
    #[\NoDiscard]
    public static function firstScalar(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_first($value);
        }

        return $value;
    }

    #[\NoDiscard]
    public static function lastScalar(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_last($value);
        }

        return $value;
    }

    /**
     * Parse common request/query boolean representations.
     *
     * Treats empty strings and the values "0", "false", "off", and "no"
     * (case-insensitive) as false. Unexpected non-scalar types are false.
     */
    #[\NoDiscard]
    public static function toBool(mixed $value): bool
    {
        return match (true) {
            $value === null => false,
            is_bool($value) => $value,
            is_int($value) => $value !== 0,
            !is_string($value) && !is_numeric($value) => false,
            default => !in_array(
                ((string) $value) |> trim(...) |> strtolower(...),
                ['', '0', 'false', 'off', 'no'],
                true,
            ),
        };
    }
}
