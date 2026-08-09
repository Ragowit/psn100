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
    public static function calculate(int $points): array
    {
        if ($points <= 5940) {
            return [
                'level' => (int) floor($points / 60) + 1,
                'progress' => (int) (floor($points / 60 * 100) % 100),
            ];
        }

        if ($points <= 14940) {
            return [
                'level' => (int) floor(($points - 5940) / 90) + 100,
                'progress' => (int) (floor(($points - 5940) / 90 * 100) % 100),
            ];
        }

        $stage = 1;
        $leftovers = $points - 14940;
        while ($leftovers > 45000 * $stage) {
            $leftovers -= 45000 * $stage;
            $stage++;
        }

        return [
            'level' => (int) floor($leftovers / (450 * $stage)) + (100 + 100 * $stage),
            'progress' => (int) (floor($leftovers / (450 * $stage) * 100) % 100),
        ];
    }
}
