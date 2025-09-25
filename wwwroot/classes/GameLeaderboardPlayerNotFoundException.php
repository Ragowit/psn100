<?php

declare(strict_types=1);

class GameLeaderboardPlayerNotFoundException extends RuntimeException
{
    private int $gameId;

    private string $gameName;

    public function __construct(int $gameId, string $gameName, string $message = 'Player not found for game')
    {
        parent::__construct($message);
        $this->gameId = $gameId;
        $this->gameName = $gameName;
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
