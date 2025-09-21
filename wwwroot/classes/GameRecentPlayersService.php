<?php

declare(strict_types=1);

class GameRecentPlayersService
{
    public const RECENT_PLAYERS_LIMIT = 10;

    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function getGame(int $gameId): ?array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                *
            FROM
                trophy_title
            WHERE
                id = :id
            SQL
        );
        $query->bindValue(':id', $gameId, PDO::PARAM_INT);
        $query->execute();

        $game = $query->fetch(PDO::FETCH_ASSOC);

        return is_array($game) ? $game : null;
    }

    public function getPlayerAccountId(string $onlineId): ?string
    {
        $onlineId = trim($onlineId);

        if ($onlineId === '') {
            return null;
        }

        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                account_id
            FROM
                player
            WHERE
                online_id = :online_id
            SQL
        );
        $query->bindValue(':online_id', $onlineId, PDO::PARAM_STR);
        $query->execute();

        $accountId = $query->fetchColumn();

        if ($accountId === false) {
            return null;
        }

        return (string) $accountId;
    }

    public function getGamePlayer(string $npCommunicationId, string $accountId): ?array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                *
            FROM
                trophy_title_player
            WHERE
                np_communication_id = :np_communication_id
                AND account_id = :account_id
            SQL
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':account_id', (int) $accountId, PDO::PARAM_INT);
        $query->execute();

        $gamePlayer = $query->fetch(PDO::FETCH_ASSOC);

        return is_array($gamePlayer) ? $gamePlayer : null;
    }

    /**
     * @param array<string, mixed> $queryParameters
     * @return array{country?: string, avatar?: string}
     */
    public function buildFilters(array $queryParameters): array
    {
        $filters = [];

        $country = trim((string) ($queryParameters['country'] ?? ''));
        if ($country !== '') {
            $filters['country'] = $country;
        }

        $avatar = trim((string) ($queryParameters['avatar'] ?? ''));
        if ($avatar !== '') {
            $filters['avatar'] = $avatar;
        }

        return $filters;
    }

    /**
     * @param array{country?: string, avatar?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function getRecentPlayers(string $npCommunicationId, array $filters): array
    {
        $sql = <<<'SQL'
            SELECT
                p.account_id,
                p.avatar_url,
                p.country,
                p.online_id AS name,
                p.trophy_count_npwr,
                p.trophy_count_sony,
                ttp.bronze,
                ttp.silver,
                ttp.gold,
                ttp.platinum,
                ttp.progress,
                ttp.last_updated_date AS last_known_date
            FROM
                trophy_title_player ttp
            JOIN player p ON ttp.account_id = p.account_id
            JOIN player_ranking r ON p.account_id = r.account_id
            WHERE
                p.status = 0
                AND r.ranking <= 10000
                AND ttp.np_communication_id = :np_communication_id
        SQL;

        $sql .= $this->buildFilterSql($filters);

        $sql .= <<<'SQL'
            ORDER BY
                ttp.last_updated_date DESC
            LIMIT :limit
        SQL;

        $query = $this->database->prepare($sql);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':limit', self::RECENT_PLAYERS_LIMIT, PDO::PARAM_INT);
        $this->bindFilterParameters($query, $filters);
        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            if (isset($row['account_id'])) {
                $row['account_id'] = (string) $row['account_id'];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array{country?: string, avatar?: string} $filters
     */
    private function buildFilterSql(array $filters): string
    {
        $sql = '';

        if (isset($filters['country'])) {
            $sql .= ' AND p.country = :country';
        }

        if (isset($filters['avatar'])) {
            $sql .= ' AND p.avatar_url = :avatar';
        }

        return $sql;
    }

    /**
     * @param array{country?: string, avatar?: string} $filters
     */
    private function bindFilterParameters(PDOStatement $query, array $filters): void
    {
        if (isset($filters['country'])) {
            $query->bindValue(':country', $filters['country'], PDO::PARAM_STR);
        }

        if (isset($filters['avatar'])) {
            $query->bindValue(':avatar', $filters['avatar'], PDO::PARAM_STR);
        }
    }
}
