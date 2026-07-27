<?php

declare(strict_types=1);

enum RouteName: string
{
    case About = 'about';
    case Avatar = 'avatar';
    case Changelog = 'changelog';
    case Game = 'game';
    case GameHistory = 'game-history';
    case GameLeaderboard = 'game-leaderboard';
    case GameRecentPlayers = 'game-recent-players';
    case Leaderboard = 'leaderboard';
    case Player = 'player';
    case Trophy = 'trophy';
}
