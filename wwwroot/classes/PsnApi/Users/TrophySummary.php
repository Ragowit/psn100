<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Users;

final class TrophySummary
{
    private int $level;

    private int $progress;

    /** @var array{bronze: int, silver: int, gold: int, platinum: int} */
    private array $earnedTrophies;

    public function __construct(int $level, int $progress, array $earnedTrophies)
    {
        $this->level = $level;
        $this->progress = $progress;
        $this->earnedTrophies = $earnedTrophies;
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
        return $this->earnedTrophies['platinum'] ?? 0;
    }

    public function gold(): int
    {
        return $this->earnedTrophies['gold'] ?? 0;
    }

    public function silver(): int
    {
        return $this->earnedTrophies['silver'] ?? 0;
    }

    public function bronze(): int
    {
        return $this->earnedTrophies['bronze'] ?? 0;
    }
}
