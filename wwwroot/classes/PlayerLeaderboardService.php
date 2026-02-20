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
}
