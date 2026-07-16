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

    #[\Override]
    protected function getPlayerProjection(): string
    {
        return <<<'SQL'
            p.online_id,
            p.avatar_url,
            p.country,
            p.level,
            p.progress,
            p.in_game_legendary,
            p.in_game_epic,
            p.in_game_rare,
            p.in_game_uncommon,
            p.in_game_common,
            p.in_game_rarity_points,
            p.in_game_rarity_rank_last_week,
            p.in_game_rarity_rank_country_last_week,
            p.trophy_count_npwr,
            p.trophy_count_sony
            SQL;
    }
}
