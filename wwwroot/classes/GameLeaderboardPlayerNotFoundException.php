<?php

declare(strict_types=1);

final class GameLeaderboardPlayerNotFoundException extends RuntimeException
{
    public function __construct(
        private readonly int $gameId,
        private readonly string $gameName,
        string $message = 'Player not found for game',
    ) {
        parent::__construct($message);
    }

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function getGameName(): string
    {
        return $this->gameName;
    }
}
