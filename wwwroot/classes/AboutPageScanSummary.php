<?php

declare(strict_types=1);

final readonly class AboutPageScanSummary
{
    public function __construct(
        final private int $scannedPlayers,
        final private int $newPlayers,
    ) {
    }

    #[\NoDiscard]
    public function getScannedPlayers(): int
    {
        return $this->scannedPlayers;
    }

    #[\NoDiscard]
    public function getNewPlayers(): int
    {
        return $this->newPlayers;
    }
}
