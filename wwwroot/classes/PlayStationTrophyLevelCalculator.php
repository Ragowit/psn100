<?php

declare(strict_types=1);

/**
 * Converts PlayStation Network trophy points into PSN level and progress percentage.
 */
final class PlayStationTrophyLevelCalculator
{
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
