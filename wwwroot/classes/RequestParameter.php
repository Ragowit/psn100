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
}
