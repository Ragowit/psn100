<?php

declare(strict_types=1);

enum HistoryDiffTokenState: string
{
    case Equal = 'equal';
    case Removed = 'removed';
    case Added = 'added';

    public function isEqual(): bool
    {
        return $this === self::Equal;
    }
}
