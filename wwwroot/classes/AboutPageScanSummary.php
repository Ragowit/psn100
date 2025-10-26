<?php

declare(strict_types=1);

class AboutPageScanSummary
{
    private int $scannedPlayers;
    private int $newPlayers;

    public function __construct(int $scannedPlayers, int $newPlayers)
    {
        $this->scannedPlayers = $scannedPlayers;
        $this->newPlayers = $newPlayers;
    }

    public function getScannedPlayers(): int
    {
        return $this->scannedPlayers;
    }

    public function getNewPlayers(): int
    {
        return $this->newPlayers;
    }
}
