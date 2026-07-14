<?php

declare(strict_types=1);

class PlayerRepository
{
    private const string SQL_UPSERT_FROM_PSN_PROFILE_MYSQL = <<<'SQL'
        INSERT INTO player (
            account_id,
            online_id,
            country,
            avatar_url,
            plus,
            about_me
        )
        VALUES (
            :account_id,
            :online_id,
            :country,
            :avatar_url,
            :plus,
            :about_me
        ) AS new ON DUPLICATE KEY
        UPDATE
            online_id = new.online_id,
            avatar_url = new.avatar_url,
            plus = new.plus,
            about_me = new.about_me
        SQL;
    private const string SQL_UPSERT_FROM_PSN_PROFILE_SQLITE = <<<'SQL'
        INSERT INTO player (
            account_id,
            online_id,
            country,
            avatar_url,
            plus,
            about_me
        )
        VALUES (
            :account_id,
            :online_id,
            :country,
            :avatar_url,
            :plus,
            :about_me
        )
        ON CONFLICT(account_id) DO UPDATE SET
            online_id = excluded.online_id,
            avatar_url = excluded.avatar_url,
            plus = excluded.plus,
            about_me = excluded.about_me
        SQL;

    public function __construct(private readonly \PDO $database) {}

    public function upsertFromPsnProfile(
        string $accountId,
        string $onlineId,
        string $country,
        string $avatarUrl,
        bool $hasPlus,
        string $aboutMe,
    ): void {
        $query = $this->database->prepare($this->upsertFromPsnProfileSql());
        $query->bindValue(':account_id', $accountId, \PDO::PARAM_STR);
        $query->bindValue(':online_id', $onlineId, \PDO::PARAM_STR);
        $query->bindValue(':country', strtolower($country), \PDO::PARAM_STR);
        $query->bindValue(':avatar_url', $avatarUrl, \PDO::PARAM_STR);
        $query->bindValue(':plus', $hasPlus, \PDO::PARAM_BOOL);
        $query->bindValue(':about_me', $aboutMe, \PDO::PARAM_STR);
        $query->execute();
    }

    private function upsertFromPsnProfileSql(): string
    {
        if ($this->database->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return self::SQL_UPSERT_FROM_PSN_PROFILE_SQLITE;
        }

        return self::SQL_UPSERT_FROM_PSN_PROFILE_MYSQL;
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
                r.rarity_ranking_country,
                r.in_game_rarity_ranking,
                r.in_game_rarity_ranking_country
            FROM
                player p
            LEFT JOIN player_ranking r ON p.account_id = r.account_id
            WHERE
                p.account_id = :account_id
        ');
        $query->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        $query->execute();
        $player = $query->fetch(\PDO::FETCH_ASSOC);

        return is_array($player) ? $player : null;
    }
}
