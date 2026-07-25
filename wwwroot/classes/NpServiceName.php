<?php

declare(strict_types=1);

require_once __DIR__ . '/Platform.php';

enum NpServiceName: string
{
    case Trophy = 'trophy';
    case Trophy2 = 'trophy2';

    #[\NoDiscard]
    public static function tryFromMixed(mixed $value): ?self
    {
        if (!is_string($value)) {
            return null;
        }

        return self::tryFrom($value |> trim(...) |> strtolower(...));
    }

    /**
     * Prefer the legacy trophy service when any platform still uses it.
     *
     * @param list<string> $platformLabels
     */
    #[\NoDiscard]
    public static function preferForPlatformLabels(array $platformLabels): self
    {
        return array_any(
            $platformLabels,
            static fn (string $platform): bool => in_array($platform, Platform::legacyTrophyServiceLabels(), true),
        ) ? self::Trophy : self::Trophy2;
    }
}
