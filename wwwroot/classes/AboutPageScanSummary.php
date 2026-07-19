<?php

declare(strict_types=1);

final readonly class AboutPageScanSummary
{
    public function __construct(private int $scannedPlayers, private int $newPlayers)
    {
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
