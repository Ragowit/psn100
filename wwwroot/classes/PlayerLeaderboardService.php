<?php

declare(strict_types=1);

require_once __DIR__ . '/AbstractPlayerLeaderboardService.php';

final readonly class PlayerLeaderboardService extends AbstractPlayerLeaderboardService
{
    #[\Override]
    protected function getRankingProjection(): string
    {
        return 'r.ranking, r.ranking_country';
    }

    #[\Override]
    protected function getOrderByExpression(): string
    {
        return 'r.ranking';
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
            p.platinum,
            p.gold,
            p.silver,
            p.bronze,
            p.points,
            p.rank_last_week,
            p.rank_country_last_week,
            p.trophy_count_npwr,
            p.trophy_count_sony
            SQL;
    }
}
