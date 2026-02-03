<?php

declare(strict_types=1);

readonly class PlayerSummary
{
    public function __construct(
        private int $numberOfGames,
        private int $numberOfCompletedGames,
        private ?float $averageProgress,
        private int $unearnedTrophies
    ) {}

    public function getNumberOfGames(): int
    {
        return $this->numberOfGames;
    }

    public function getNumberOfCompletedGames(): int
    {
        return $this->numberOfCompletedGames;
    }

    public function getAverageProgress(): ?float
    {
        return $this->averageProgress;
    }

    public function getUnearnedTrophies(): int
    {
        return $this->unearnedTrophies;
    }
}
