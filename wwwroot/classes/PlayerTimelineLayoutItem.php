<?php

declare(strict_types=1);

final readonly class PlayerTimelineLayoutItem
{
    public function __construct(
        private PlayerTimelineEntry $entry,
        private int $offsetDays,
        private int $durationDays,
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
