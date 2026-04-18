<?php

declare(strict_types=1);

enum PlayStationClientMode: string
{
    case Legacy = 'legacy';
    case New = 'new';

    public static function fromEnvironmentValue(mixed $value, self $default = self::Legacy): self
    {
        if (!is_string($value)) {
            return $default;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            self::Legacy->value => self::Legacy,
            self::New->value => self::New,
            default => $default,
        };
    }
}
