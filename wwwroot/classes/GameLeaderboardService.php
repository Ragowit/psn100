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
     * @param array<string, mixed> $queryParameters
     */
    public function getPage(array $queryParameters): int
    {
        $page = $queryParameters['page'] ?? 1;

        if (is_numeric($page)) {
            $page = (int) $page;
        } else {
            $page = 1;
        }

        return max($page, 1);
    }

    public function calculateOffset(int $page, int $limit): int
    {
        return ($page - 1) * $limit;
    }

    /**
     * @param array{country?: string, avatar?: string} $filters
     */
    public function getLeaderboardPlayerCount(string $npCommunicationId, array $filters): int
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

        $sql .= $this->buildFilterSql($filters);

        $query = $this->database->prepare($sql);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $this->bindFilterParameters($query, $filters);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    /**
     * @param array{country?: string, avatar?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function getLeaderboardRows(string $npCommunicationId, array $filters, int $offset, int $limit): array
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
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
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
        $clauses = '';

        if (isset($filters['country'])) {
            $clauses .= "\n                AND p.country = :country";
        }

        if (isset($filters['avatar'])) {
            $clauses .= "\n                AND p.avatar_url = :avatar";
        }

        return $clauses;
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
