<?php

declare(strict_types=1);

final class RequestParameter
{
    public static function firstScalar(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_first($value);
        }

        return $value;
    }

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
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        $normalized = ((string) $value) |> trim(...) |> strtolower(...);

        return !in_array($normalized, ['', '0', 'false', 'off', 'no'], true);
    }
}
