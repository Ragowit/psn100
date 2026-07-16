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

    #[\Override]
    protected function getPlayerProjection(): string
    {
        return <<<'SQL'
            p.account_id,
            p.online_id,
            p.avatar_url,
            p.country,
            p.level,
            p.progress,
            p.legendary,
            p.epic,
            p.rare,
            p.uncommon,
            p.common,
            p.rarity_points,
            p.rarity_rank_last_week,
            p.rarity_rank_country_last_week,
            p.trophy_count_npwr,
            p.trophy_count_sony
            SQL;
    }
}
