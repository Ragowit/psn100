<?php

declare(strict_types=1);

enum AvatarSize: string
{
    case Xl = 'xl';
    case L = 'l';
    case M = 'm';
    case S = 's';

    /**
     * Preferred download order when synchronizing a player avatar from PSN.
     *
     * @return list<self>
     */
    #[\NoDiscard]
    public static function preferenceOrder(): array
    {
        return [self::Xl, self::L, self::M, self::S];
    }
}
