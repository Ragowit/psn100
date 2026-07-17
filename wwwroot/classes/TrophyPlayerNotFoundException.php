<?php

declare(strict_types=1);

class TrophyPlayerNotFoundException extends RuntimeException
{
    public function __construct(
        private string $trophyId,
        private string $trophyName,
    ) {
        parent::__construct('Player not found for trophy.');
    }

    public function getTrophyId(): string
    {
        return $this->trophyId;
    }

    public function getTrophyName(): string
    {
        return $this->trophyName;
    }
}
