<?php

declare(strict_types=1);

class GameLeaderboardService
{
    public const PAGE_SIZE = 50;

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

    public function getLeaderboardPlayerCount(string $npCommunicationId, GamePlayerFilter $filter): int
    {
        $sql = <<<'SQL'
            SELECT
                COUNT(*)
            FROM
                trophy_title_player ttp
            JOIN player p ON p.account_id = ttp.account_id
            JOIN player_ranking r ON r.account_id = p.account_id
            WHERE
                ttp.np_communication_id = :np_communication_id
                AND p.status = 0
                AND r.ranking <= 10000
        SQL;

        $sql .= $this->buildFilterSql($filter);

        $query = $this->database->prepare($sql);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $this->bindFilterParameters($query, $filter);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLeaderboardRows(string $npCommunicationId, GameLeaderboardFilter $filter, int $limit): array
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

        $sql .= $this->buildFilterSql($filter);

        $sql .= <<<'SQL'
            ORDER BY
                ttp.progress DESC,
                ttp.platinum DESC,
                ttp.gold DESC,
                ttp.silver DESC,
                ttp.bronze DESC,
                ttp.last_updated_date
            LIMIT :offset, :limit
        SQL;

        $query = $this->database->prepare($sql);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':offset', $filter->getOffset($limit), PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $this->bindFilterParameters($query, $filter);
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

    private function buildFilterSql(GamePlayerFilter $filter): string
    {
        $clauses = '';

        if ($filter->hasCountry()) {
            $clauses .= " AND p.country = :country";
        }

        if ($filter->hasAvatar()) {
            $clauses .= " AND p.avatar_url = :avatar";
        }

        return $clauses;
    }

    private function bindFilterParameters(PDOStatement $query, GamePlayerFilter $filter): void
    {
        if ($filter->hasCountry()) {
            $query->bindValue(':country', (string) $filter->getCountry(), PDO::PARAM_STR);
        }

        if ($filter->hasAvatar()) {
            $query->bindValue(':avatar', (string) $filter->getAvatar(), PDO::PARAM_STR);
        }
    }
}
