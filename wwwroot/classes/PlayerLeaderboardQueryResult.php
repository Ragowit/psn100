<?php

declare(strict_types=1);

final readonly class PlayerLeaderboardQueryResult
{
    /**
     * @param array<int, array<string, mixed>> $players
     */
    public function __construct(
        public array $players,
        public ?int $totalPlayers,
    ) {
    }
}
