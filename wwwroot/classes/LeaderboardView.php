<?php

declare(strict_types=1);

enum LeaderboardView: string
{
    case Difficulty = 'difficulty';
    case Main = 'main';
    case Rarity = 'rarity';
    case Trophy = 'trophy';

    private const string LEGACY_IN_GAME_RARITY = 'in-game-rarity';

    #[\NoDiscard]
    public function includeFile(): string
    {
        return match ($this) {
            self::Main, self::Trophy => 'leaderboard_main.php',
            self::Rarity => 'leaderboard_rarity.php',
            self::Difficulty => 'leaderboard_difficulty.php',
        };
    }

    #[\NoDiscard]
    public static function isLegacyPath(string $segment): bool
    {
        return $segment === self::LEGACY_IN_GAME_RARITY;
    }
}
