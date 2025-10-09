<?php

declare(strict_types=1);

final class SearchQueryHelper
{
    /**
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    public static function addFulltextSelectColumns(
        array $columns,
        string $column,
        bool $includeScore,
        string $searchTerm
    ): array {
        if (!$includeScore) {
            return $columns;
        }

        $columns[] = sprintf('%s = :search AS exact_match', $column);

        if ($searchTerm !== '') {
            $columns[] = sprintf('%s LIKE :search_prefix AS prefix_match', $column);
        } else {
            $columns[] = '0 AS prefix_match';
        }

        $columns[] = sprintf('MATCH(%s) AGAINST (:search) AS score', $column);

        return $columns;
    }

    /**
     * @param array<int, string> $conditions
     * @return array<int, string>
     */
    public static function appendFulltextCondition(
        array $conditions,
        bool $shouldApply,
        string $column,
        string $searchTerm
    ): array {
        if (!$shouldApply) {
            return $conditions;
        }

        $matchCondition = sprintf('(MATCH(%s) AGAINST (:search)) > 0', $column);

        if ($searchTerm !== '') {
            $conditions[] = sprintf('(%s OR %s LIKE :search_like)', $matchCondition, $column);
        } else {
            $conditions[] = $matchCondition;
        }

        return $conditions;
    }

    public static function bindSearchParameters(\PDOStatement $statement, string $searchTerm, bool $bindPrefix): void
    {
        $statement->bindValue(':search', $searchTerm, \PDO::PARAM_STR);

        if ($searchTerm === '') {
            return;
        }

        $statement->bindValue(':search_like', self::buildSearchLikeParameter($searchTerm), \PDO::PARAM_STR);

        if ($bindPrefix) {
            $statement->bindValue(':search_prefix', self::buildSearchPrefixParameter($searchTerm), \PDO::PARAM_STR);
        }
    }

    public static function buildSearchLikeParameter(string $search): string
    {
        return '%' . addcslashes($search, "\\%_") . '%';
    }

    public static function buildSearchPrefixParameter(string $search): string
    {
        return addcslashes($search, "\\%_") . '%';
    }
}
