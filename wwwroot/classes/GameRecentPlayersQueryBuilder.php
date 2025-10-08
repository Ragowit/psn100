<?php

declare(strict_types=1);

require_once __DIR__ . '/GamePlayerFilter.php';

final class GameRecentPlayersQueryBuilder
{
    private const BASE_QUERY = <<<'SQL'
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

    private const ORDER_BY_QUERY = <<<'SQL'
        ORDER BY
            ttp.last_updated_date DESC
        LIMIT :limit
    SQL;

    private GamePlayerFilter $filter;

    private int $limit;

    public function __construct(GamePlayerFilter $filter, int $limit)
    {
        $this->filter = $filter;
        $this->limit = $limit;
    }

    public function prepare(\PDO $database, string $npCommunicationId): \PDOStatement
    {
        $query = $database->prepare($this->buildSql());
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':limit', $this->limit, PDO::PARAM_INT);
        $this->bindFilterParameters($query);

        return $query;
    }

    private function buildSql(): string
    {
        return self::BASE_QUERY
            . $this->buildFilterSql()
            . self::ORDER_BY_QUERY;
    }

    private function buildFilterSql(): string
    {
        $clauses = [];

        if ($this->filter->hasCountry()) {
            $clauses[] = 'p.country = :country';
        }

        if ($this->filter->hasAvatar()) {
            $clauses[] = 'p.avatar_url = :avatar';
        }

        if ($clauses === []) {
            return '';
        }

        return ' AND ' . implode(' AND ', $clauses);
    }

    private function bindFilterParameters(\PDOStatement $query): void
    {
        if ($this->filter->hasCountry()) {
            $query->bindValue(':country', (string) $this->filter->getCountry(), PDO::PARAM_STR);
        }

        if ($this->filter->hasAvatar()) {
            $query->bindValue(':avatar', (string) $this->filter->getAvatar(), PDO::PARAM_STR);
        }
    }
}
