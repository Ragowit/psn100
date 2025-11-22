<?php

declare(strict_types=1);

require_once __DIR__ . '/AbstractLeaderboardPageContext.php';
require_once __DIR__ . '/../PlayerInGameRarityLeaderboardService.php';
require_once __DIR__ . '/InGameRarityLeaderboardRow.php';

class InGameRarityLeaderboardPageContext extends AbstractLeaderboardPageContext
{
    private const TITLE = 'PSN Rarity (In-Game) Leaderboard ~ PSN 100%';

    public function getTitle(): string
    {
        return self::TITLE;
    }

    protected static function createDataProvider(PDO $database): PlayerLeaderboardDataProvider
    {
        return new PlayerInGameRarityLeaderboardService($database);
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
        return new InGameRarityLeaderboardRow(
            $player,
            $filter,
            $utility,
            $highlightedPlayerId,
            $filterParameters
        );
    }
}
