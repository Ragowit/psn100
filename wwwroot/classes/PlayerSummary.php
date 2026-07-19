<?php

declare(strict_types=1);

final readonly class PlayerSummary
{
    public function __construct(
        final private int $numberOfGames,
        final private int $numberOfCompletedGames,
        final private ?float $averageProgress,
        final private int $unearnedTrophies
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
