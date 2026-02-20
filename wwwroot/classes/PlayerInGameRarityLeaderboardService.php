<?php

declare(strict_types=1);

require_once __DIR__ . '/AbstractPlayerLeaderboardService.php';

final readonly class PlayerInGameRarityLeaderboardService extends AbstractPlayerLeaderboardService
{
    #[\Override]
    protected function getRankingProjection(): string
    {
        return 'r.in_game_rarity_ranking AS ranking, r.in_game_rarity_ranking_country AS ranking_country';
    }

    #[\Override]
    protected function getOrderByExpression(): string
    {
        return 'r.in_game_rarity_ranking';
    }
}
