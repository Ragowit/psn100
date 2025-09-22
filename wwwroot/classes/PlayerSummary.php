<?php

declare(strict_types=1);

class PlayerSummary
{
    private int $numberOfGames;

    private int $numberOfCompletedGames;

    private ?float $averageProgress;

    private int $unearnedTrophies;

    public function __construct(int $numberOfGames, int $numberOfCompletedGames, ?float $averageProgress, int $unearnedTrophies)
    {
        $this->numberOfGames = $numberOfGames;
        $this->numberOfCompletedGames = $numberOfCompletedGames;
        $this->averageProgress = $averageProgress;
        $this->unearnedTrophies = $unearnedTrophies;
    }

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

