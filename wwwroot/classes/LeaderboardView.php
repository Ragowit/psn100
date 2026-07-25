<?php

declare(strict_types=1);

enum LeaderboardView: string
{
    case Main = 'main';
    case Trophy = 'trophy';
    case Rarity = 'rarity';
    case InGameRarity = 'in-game-rarity';

    #[\NoDiscard]
    public function includeFile(): string
    {
        return match ($this) {
            self::Main, self::Trophy => 'leaderboard_main.php',
            self::Rarity => 'leaderboard_rarity.php',
            self::InGameRarity => 'leaderboard_in_game_rarity.php',
        };
    }
}
