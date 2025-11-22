<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';

class WeeklyCronJob implements CronJobInterface
{
    private const UPDATE_PLAYER_RANKINGS_QUERY = <<<'SQL'
        UPDATE player p
        JOIN player_ranking r ON p.account_id = r.account_id
        SET
            p.rank_last_week = r.ranking,
            p.rarity_rank_last_week = r.rarity_ranking,
            p.in_game_rarity_rank_last_week = r.in_game_rarity_ranking,
            p.rank_country_last_week = r.ranking_country,
            p.rarity_rank_country_last_week = r.rarity_ranking_country,
            p.in_game_rarity_rank_country_last_week = r.in_game_rarity_ranking_country
        WHERE p.status = 0
        SQL;

    private const RESET_INACTIVE_RANKINGS_QUERY = <<<'SQL'
        UPDATE
            player p
        SET
            p.rank_last_week = 0,
            p.rank_country_last_week = 0,
            p.rarity_rank_last_week = 0,
            p.rarity_rank_country_last_week = 0,
            p.in_game_rarity_rank_last_week = 0,
            p.in_game_rarity_rank_country_last_week = 0
        WHERE
            p.status != 0
        SQL;

    private PDO $database;

    private int $retryDelaySeconds;

    public function __construct(PDO $database, int $retryDelaySeconds = 3)
    {
        $this->database = $database;
        $this->retryDelaySeconds = $retryDelaySeconds;
    }

    public function run(): void
    {
        $this->executeWithRetry([$this, 'updateLeaderboardsForActivePlayers']);
        $this->resetRankingsForInactivePlayers();
    }

    private function updateLeaderboardsForActivePlayers(): void
    {
        $query = $this->database->prepare(self::UPDATE_PLAYER_RANKINGS_QUERY);
        $query->execute();
    }

    private function resetRankingsForInactivePlayers(): void
    {
        $query = $this->database->prepare(self::RESET_INACTIVE_RANKINGS_QUERY);
        $query->execute();
    }

    private function executeWithRetry(callable $operation): void
    {
        while (true) {
            try {
                $operation();

                return;
            } catch (Exception $exception) {
                sleep($this->retryDelaySeconds);
            }
        }
    }
}
