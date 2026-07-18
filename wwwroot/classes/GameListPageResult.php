<?php

declare(strict_types=1);

final readonly class GameListPageResult
{
    /**
     * @param list<GameListItem> $games
     */
    public function __construct(
        final public array $games,
        final public int $totalGames,
    ) {
    }
}

