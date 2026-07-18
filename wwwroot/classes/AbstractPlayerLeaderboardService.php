<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerLeaderboardDataProvider.php';
require_once __DIR__ . '/PlayerLeaderboardQueryResult.php';

abstract readonly class AbstractPlayerLeaderboardService implements PlayerLeaderboardDataProvider
{
    public const int PAGE_SIZE = 50;

    private const string COUNT_SQL = <<<'SQL'
        SELECT
            COUNT(*)
        FROM
            player_ranking r
        JOIN player p ON p.account_id = r.account_id
        WHERE
            p.status = 0
    SQL;

    public function __construct(protected \PDO $database)
    {
    }

    #[\Override]
    final public function countPlayers(PlayerLeaderboardFilter $filter): int
    {
        $query = $this->database->prepare(self::COUNT_SQL . $this->buildFilterSql($filter));
        $this->bindFilterParameters($query, $filter);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    #[\Override]
    final public function getPlayers(PlayerLeaderboardFilter $filter, int $limit = self::PAGE_SIZE): array
    {
        return $this->fetchPlayerRows($filter, $limit, false);
    }

    final public function getPlayersWithTotal(
        PlayerLeaderboardFilter $filter,
        int $limit = self::PAGE_SIZE,
    ): PlayerLeaderboardQueryResult {
        $rows = $this->fetchPlayerRows($filter, $limit, true);

        if ($rows === []) {
            return new PlayerLeaderboardQueryResult([], null);
        }

        $firstRow = array_first($rows);
        $totalPlayers = is_numeric($firstRow['total_rows'] ?? null)
            ? max(0, (int) $firstRow['total_rows'])
            : null;

        $players = array_map(
            static function (array $row): array {
                unset($row['total_rows']);

                return $row;
            },
            $rows
        );

        return new PlayerLeaderboardQueryResult($players, $totalPlayers);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPlayerRows(
        PlayerLeaderboardFilter $filter,
        int $limit,
        bool $includeTotalCount,
    ): array {
        $totalCountProjection = $includeTotalCount ? ",\n                COUNT(*) OVER() AS total_rows" : '';

        $sql = <<<SQL
            SELECT
                {$this->getPlayerProjection()},
                {$this->getRankingProjection()}{$totalCountProjection}
            FROM
                player_ranking r
            JOIN player p ON p.account_id = r.account_id
            WHERE
                p.status = 0
        SQL;

        $sql .= $this->buildFilterSql($filter);

        $sql .= <<<SQL
            ORDER BY
                {$this->getOrderByExpression()}
            LIMIT :limit OFFSET :offset
        SQL;

        $query = $this->database->prepare($sql);
        $query->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $query->bindValue(':offset', $filter->getOffset($limit), \PDO::PARAM_INT);
        $this->bindFilterParameters($query, $filter);
        $query->execute();

        $rows = $query->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    #[\Override]
    final public function getPageSize(): int
    {
        return self::PAGE_SIZE;
    }

    abstract protected function getRankingProjection(): string;

    abstract protected function getOrderByExpression(): string;

    /**
     * Explicit player columns used by the leaderboard row mappers.
     * Avoid SELECT p.* so about_me and unused rarity buckets are not fetched.
     */
    abstract protected function getPlayerProjection(): string;

    final protected function buildFilterSql(PlayerLeaderboardFilter $filter): string
    {
        $clauses = [];

        if ($filter->hasCountry()) {
            $clauses[] = 'p.country = :country';
        }

        if ($filter->hasAvatar()) {
            $clauses[] = 'p.avatar_url = :avatar';
        }

        if ($clauses === []) {
            return '';
        }

        return ' AND ' . implode(' AND ', $clauses);
    }

    final protected function bindFilterParameters(\PDOStatement $query, PlayerLeaderboardFilter $filter): void
    {
        if ($filter->hasCountry()) {
            $query->bindValue(':country', (string) $filter->getCountry(), \PDO::PARAM_STR);
        }

        if ($filter->hasAvatar()) {
            $query->bindValue(':avatar', (string) $filter->getAvatar(), \PDO::PARAM_STR);
        }
    }
}
