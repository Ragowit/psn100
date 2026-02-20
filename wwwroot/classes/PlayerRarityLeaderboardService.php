<?php

declare(strict_types=1);

require_once __DIR__ . '/AbstractPlayerLeaderboardService.php';

final readonly class PlayerRarityLeaderboardService extends AbstractPlayerLeaderboardService
{
    #[\Override]
    protected function getRankingProjection(): string
    {
        return 'r.rarity_ranking AS ranking, r.rarity_ranking_country AS ranking_country';
    }

    #[\Override]
    protected function getOrderByExpression(): string
    {
        return 'r.rarity_ranking';
    }
}
