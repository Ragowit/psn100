<?php

declare(strict_types=1);

final readonly class PlayerTimelineData
{
    /**
     * @param PlayerTimelineEntry[] $entries
     */
    public function __construct(
        private DateTimeImmutable $startDate,
        private DateTimeImmutable $endDate,
        private array $entries,
    ) {
    }

    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    /**
     * @return PlayerTimelineEntry[]
     */
    public function getEntries(): array
    {
        return $this->entries;
    }
}
