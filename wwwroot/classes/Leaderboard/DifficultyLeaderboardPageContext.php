<?php

declare(strict_types=1);

require_once __DIR__ . '/AbstractLeaderboardPageContext.php';
require_once __DIR__ . '/../PlayerDifficultyLeaderboardService.php';
require_once __DIR__ . '/DifficultyLeaderboardRow.php';

final class DifficultyLeaderboardPageContext extends AbstractLeaderboardPageContext
{
    private const string TITLE = 'PSN Difficulty Leaderboard ~ PSN 100%';

    #[\Override]
    public function getTitle(): string
    {
        return self::TITLE;
    }

    #[\Override]
    protected static function createDataProvider(PDO $database): PlayerLeaderboardDataProvider
    {
        return new PlayerDifficultyLeaderboardService($database);
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
        return new DifficultyLeaderboardRow(
            $player,
            $filter,
            $utility,
            $highlightedPlayerId,
            $filterParameters
        );
    }
}
