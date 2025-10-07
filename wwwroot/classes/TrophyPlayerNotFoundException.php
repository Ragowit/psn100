<?php

declare(strict_types=1);

class TrophyPlayerNotFoundException extends RuntimeException
{
    private string $trophyId;

    private string $trophyName;

    public function __construct(string $trophyId, string $trophyName)
    {
        parent::__construct('Player not found for trophy.');
        $this->trophyId = $trophyId;
        $this->trophyName = $trophyName;
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
