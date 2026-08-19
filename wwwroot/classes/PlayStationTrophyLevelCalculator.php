<?php

declare(strict_types=1);

/**
 * Converts PlayStation Network trophy points into PSN level and progress percentage.
 */
final readonly class PlayStationTrophyLevelCalculator
{
    private const int BRONZE_POINTS = 15;
    private const int SILVER_POINTS = 30;
    private const int GOLD_POINTS = 90;
    private const int PLATINUM_POINTS = 300;

    /**
     * Returns the PSN trophy score for the given trophy counts.
     */
    #[\NoDiscard('The calculated trophy points must be used.')]
    public static function calculateTrophyPoints(int $bronze, int $silver, int $gold, int $platinum): int
    {
        return ($bronze * self::BRONZE_POINTS)
            + ($silver * self::SILVER_POINTS)
            + ($gold * self::GOLD_POINTS)
            + ($platinum * self::PLATINUM_POINTS);
    }

    /**
     * @return array{level: int, progress: int}
     */
    #[\NoDiscard('The calculated trophy level and progress must be used.')]
    public static function calculate(int $points): array
    {
        if ($points <= 5940) {
            return self::calculateTier($points, 60, 1);
        }

        if ($points <= 14940) {
            return self::calculateTier($points - 5940, 90, 100);
        }

        $stage = 1;
        $leftovers = $points - 14940;
        while ($leftovers > 45000 * $stage) {
            $leftovers -= 45000 * $stage;
            $stage++;
        }

        return self::calculateTier($leftovers, 450 * $stage, 100 + 100 * $stage);
    }

    /**
     * @return array{level: int, progress: int}
     */
    private static function calculateTier(int $points, int $pointsPerLevel, int $baseLevel): array
    {
        return [
            'level' => intdiv($points, $pointsPerLevel) + $baseLevel,
            'progress' => intdiv(($points % $pointsPerLevel) * 100, $pointsPerLevel),
        ];
    }
}
