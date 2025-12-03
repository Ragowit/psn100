<?php

declare(strict_types=1);

enum NavigationSection: string
{
    case Home = 'home';
    case Leaderboard = 'leaderboard';
    case Game = 'game';
    case Trophy = 'trophy';
    case Avatar = 'avatar';
    case About = 'about';

    public static function fromName(string $section): ?self
    {
        return self::tryFrom(strtolower($section));
    }
}
