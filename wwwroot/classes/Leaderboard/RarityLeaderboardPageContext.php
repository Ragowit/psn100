<?php

declare(strict_types=1);

require_once __DIR__ . '/AbstractLeaderboardPageContext.php';
require_once __DIR__ . '/../PlayerRarityLeaderboardService.php';
require_once __DIR__ . '/RarityLeaderboardRow.php';

class RarityLeaderboardPageContext extends AbstractLeaderboardPageContext
{
    private const TITLE = 'PSN Rarity Leaderboard ~ PSN 100%';

    public function getTitle(): string
    {
        return self::TITLE;
    }

    protected static function createDataProvider(PDO $database): PlayerLeaderboardDataProvider
    {
        return new PlayerRarityLeaderboardService($database);
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
        return new RarityLeaderboardRow(
            $player,
            $filter,
            $utility,
            $highlightedPlayerId,
            $filterParameters
        );
    }
}
