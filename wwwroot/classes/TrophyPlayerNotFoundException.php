<?php

declare(strict_types=1);

final class TrophyPlayerNotFoundException extends RuntimeException
{
    public function __construct(
        final private readonly string $trophyId,
        final private readonly string $trophyName,
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
