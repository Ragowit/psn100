<?php

declare(strict_types=1);

final readonly class PlayerTimelineLayoutItem
{
    public function __construct(
        final private PlayerTimelineEntry $entry,
        final private int $offsetDays,
        final private int $durationDays,
    ) {
    }

    public function getEntry(): PlayerTimelineEntry
    {
        return $this->entry;
    }

    public function getOffsetDays(): int
    {
        return $this->offsetDays;
    }

    public function getDurationDays(): int
    {
        return $this->durationDays;
    }
}
