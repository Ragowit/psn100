<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerLeaderboardFilter.php';

interface PlayerLeaderboardDataProvider
{
    public function countPlayers(PlayerLeaderboardFilter $filter): int;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPlayers(PlayerLeaderboardFilter $filter, int $limit): array;

    public function getPageSize(): int;
}
