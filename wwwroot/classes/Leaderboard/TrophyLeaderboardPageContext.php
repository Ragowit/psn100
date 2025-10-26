<?php

declare(strict_types=1);

require_once __DIR__ . '/AbstractLeaderboardPageContext.php';
require_once __DIR__ . '/../PlayerLeaderboardService.php';
require_once __DIR__ . '/TrophyLeaderboardRow.php';

class TrophyLeaderboardPageContext extends AbstractLeaderboardPageContext
{
    private const TITLE = 'PSN Trophy Leaderboard ~ PSN 100%';

    public function getTitle(): string
    {
        return self::TITLE;
    }

    protected static function createDataProvider(PDO $database): PlayerLeaderboardDataProvider
    {
        return new PlayerLeaderboardService($database);
    }

    /**
     * @param array<string, mixed> $player
     */
    protected function createRow(
        array $player,
        PlayerLeaderboardFilter $filter,
        Utility $utility,
        ?string $highlightedPlayerId,
        array $filterParameters
    ): AbstractLeaderboardRow {
        return new TrophyLeaderboardRow(
            $player,
            $filter,
            $utility,
            $highlightedPlayerId,
            $filterParameters
        );
    }
}
