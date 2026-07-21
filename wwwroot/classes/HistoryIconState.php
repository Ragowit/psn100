<?php

declare(strict_types=1);

enum HistoryIconState: string
{
    case Previous = 'previous';
    case Current = 'current';

    public function borderClass(): string
    {
        return $this === self::Previous ? 'border-danger' : 'border-success';
    }
}
