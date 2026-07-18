<?php

declare(strict_types=1);

final readonly class PlayerLeaderboardQueryResult
{
    /**
     * @param array<int, array<string, mixed>> $players
     */
    public function __construct(
        final public array $players,
        final public ?int $totalPlayers,
    ) {
    }
}
