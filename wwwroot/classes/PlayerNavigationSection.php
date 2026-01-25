<?php

declare(strict_types=1);

enum PlayerNavigationSection: string
{
    case GAMES = 'games';
    case TIMELINE = 'timeline';
    case LOG = 'log';
    case TROPHY_ADVISOR = 'trophy-advisor';
    case GAME_ADVISOR = 'game-advisor';
    case RANDOM = 'random';
}
