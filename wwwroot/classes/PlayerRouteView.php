<?php

declare(strict_types=1);

enum PlayerRouteView: string
{
    case Advisor = 'advisor';
    case Log = 'log';
    case Random = 'random';
    case Report = 'report';
    case Timeline = 'timeline';

    #[\NoDiscard]
    public function includeFile(): string
    {
        return match ($this) {
            self::Advisor => 'player_advisor.php',
            self::Log => 'player_log.php',
            self::Random => 'player_random.php',
            self::Report => 'player_report.php',
            self::Timeline => 'player_timeline.php',
        };
    }
}
