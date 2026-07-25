<?php

declare(strict_types=1);

enum PlayerTimelineStatus: string
{
    case Completed = 'completed';
    case Stalled = 'stalled';
    case Playing = 'playing';

    public function cssClass(): string
    {
        return $this->value;
    }
}
