<?php

declare(strict_types=1);

final class PsnTrophySummaryDto
{
    public function __construct(
        private readonly int $level,
        private readonly int $progress,
        private readonly int $platinum,
        private readonly int $gold,
        private readonly int $silver,
        private readonly int $bronze
    ) {
    }

    public function level(): int
    {
        return $this->level;
    }

    public function progress(): int
    {
        return $this->progress;
    }

    public function platinum(): int
    {
        return $this->platinum;
    }

    public function gold(): int
    {
        return $this->gold;
    }

    public function silver(): int
    {
        return $this->silver;
    }

    public function bronze(): int
    {
        return $this->bronze;
    }
}
