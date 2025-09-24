<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerLeaderboardDataProvider.php';

class PlayerLeaderboardService implements PlayerLeaderboardDataProvider
{
    public const PAGE_SIZE = 50;

    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function countPlayers(PlayerLeaderboardFilter $filter): int
    {
        $sql = <<<'SQL'
            SELECT
                COUNT(*)
            FROM
                player p
            WHERE
                p.status = 0
        SQL;

        $sql .= $this->buildFilterSql($filter);

        $query = $this->database->prepare($sql);
        $this->bindFilterParameters($query, $filter);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPlayers(PlayerLeaderboardFilter $filter, int $limit = self::PAGE_SIZE): array
    {
        $sql = <<<'SQL'
            SELECT
                p.*,
                r.ranking,
                r.ranking_country
            FROM
                player p
            JOIN player_ranking r ON p.account_id = r.account_id
            WHERE
                p.status = 0
        SQL;

        $sql .= $this->buildFilterSql($filter);

        $sql .= <<<'SQL'
            ORDER BY
                r.ranking
            LIMIT :offset, :limit
        SQL;

        $query = $this->database->prepare($sql);
        $query->bindValue(':offset', $filter->getOffset($limit), PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $this->bindFilterParameters($query, $filter);
        $query->execute();

        $players = $query->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($players)) {
            return [];
        }

        return $players;
    }

    public function getPageSize(): int
    {
        return self::PAGE_SIZE;
    }

    private function buildFilterSql(PlayerLeaderboardFilter $filter): string
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

    private function bindFilterParameters(PDOStatement $query, PlayerLeaderboardFilter $filter): void
    {
        if ($filter->hasCountry()) {
            $query->bindValue(':country', (string) $filter->getCountry(), PDO::PARAM_STR);
        }

        if ($filter->hasAvatar()) {
            $query->bindValue(':avatar', (string) $filter->getAvatar(), PDO::PARAM_STR);
        }
    }
}
