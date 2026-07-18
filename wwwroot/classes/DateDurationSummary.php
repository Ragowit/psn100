<?php

declare(strict_types=1);

/**
 * Formats a date interval into its most significant non-zero duration parts.
 */
final class DateDurationSummary
{
    /**
     * @return list<string>
     */
    public static function significantParts(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        int $maxParts = 2,
    ): array {
        if ($maxParts < 1) {
            return [];
        }

        $formatted = $start->diff($end)->format(
            '%y years, %m months, %d days, %h hours, %i minutes, %s seconds'
        );

        return explode(', ', $formatted)
            |> (fn(array $parts): array => array_filter(
                $parts,
                static fn(string $part): bool => $part !== '' && $part[0] !== '0'
            ))
            |> array_values(...)
            |> (fn(array $parts): array => array_slice($parts, 0, $maxParts));
    }
}
