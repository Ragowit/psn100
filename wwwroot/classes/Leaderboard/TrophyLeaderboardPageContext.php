<?php

declare(strict_types=1);

require_once __DIR__ . '/AbstractLeaderboardPageContext.php';
require_once __DIR__ . '/../PlayerLeaderboardService.php';
require_once __DIR__ . '/TrophyLeaderboardRow.php';

final class TrophyLeaderboardPageContext extends AbstractLeaderboardPageContext
{
    private const string TITLE = 'PSN Trophy Leaderboard ~ PSN 100%';

    #[\Override]
    public function getTitle(): string
    {
        return self::TITLE;
    }

    #[\Override]
    protected static function createDataProvider(PDO $database): PlayerLeaderboardDataProvider
    {
        return new PlayerLeaderboardService($database);
    }

    /**
     * @param array<string, mixed> $player
     */
    #[\Override]
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
