<?php

declare(strict_types=1);

final readonly class GameListPageResult
{
    /**
     * @param list<GameListItem> $games
     */
    public function __construct(
        public array $games,
        public int $totalGames
    ) {
    }
}

