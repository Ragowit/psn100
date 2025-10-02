<?php

declare(strict_types=1);

class PlayerRepository
{
    private \PDO $database;

    public function __construct(\PDO $database)
    {
        $this->database = $database;
    }

    public function findAccountIdByOnlineId(string $onlineId): ?int
    {
        $query = $this->database->prepare('SELECT account_id FROM player WHERE online_id = :online_id');
        $query->bindValue(':online_id', $onlineId, \PDO::PARAM_STR);
        $query->execute();
        $accountId = $query->fetchColumn();

        if ($accountId === false) {
            return null;
        }

        return (int) $accountId;
    }

    public function fetchPlayerByAccountId(int $accountId): ?array
    {
        $query = $this->database->prepare('
            SELECT
                p.*,
                r.ranking,
                r.rarity_ranking,
                r.ranking_country,
                r.rarity_ranking_country
            FROM
                player p
            LEFT JOIN player_ranking r ON p.account_id = r.account_id
            WHERE
                p.account_id = :account_id
        ');
        $query->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        $query->execute();
        $player = $query->fetch();

        return is_array($player) ? $player : null;
    }
}
