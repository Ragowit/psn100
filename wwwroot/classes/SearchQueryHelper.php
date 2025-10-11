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

        $variants = self::getSearchVariants($searchTerm);

        if ($variants === []) {
            $columns[] = '0 AS exact_match';
        } else {
            $exactConditions = [];
            foreach ($variants as $index => $_) {
                $exactConditions[] = sprintf('%s = :search_fulltext_%d', $column, $index);
            }

            $columns[] = sprintf('(%s) AS exact_match', implode(' OR ', $exactConditions));
        }

        if ($searchTerm !== '' && $variants !== []) {
            $prefixConditions = [];
            foreach ($variants as $index => $_) {
                $prefixConditions[] = sprintf('%s LIKE :search_prefix_%d', $column, $index);
            }

            $columns[] = sprintf('(%s) AS prefix_match', implode(' OR ', $prefixConditions));
        } else {
            $columns[] = '0 AS prefix_match';
        }

        if ($variants === []) {
            $columns[] = sprintf('MATCH(%s) AGAINST (:search_fulltext_0) AS score', $column);
        } else {
            $scoreExpressions = [];
            foreach ($variants as $index => $_) {
                $scoreExpressions[] = sprintf('MATCH(%s) AGAINST (:search_fulltext_%d)', $column, $index);
            }

            if (count($scoreExpressions) === 1) {
                $columns[] = sprintf('%s AS score', $scoreExpressions[0]);
            } else {
                $columns[] = sprintf('GREATEST(%s) AS score', implode(', ', $scoreExpressions));
            }
        }

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

        $variants = self::getSearchVariants($searchTerm);

        if ($variants === []) {
            return $conditions;
        }

        $matchConditions = [];
        foreach ($variants as $index => $_) {
            $matchConditions[] = sprintf('(MATCH(%s) AGAINST (:search_fulltext_%d)) > 0', $column, $index);
        }

        $matchClause = implode(' OR ', $matchConditions);

        if ($searchTerm !== '') {
            $likeConditions = [];
            foreach ($variants as $index => $_) {
                $likeConditions[] = sprintf('%s LIKE :search_like_%d', $column, $index);
            }

            $conditions[] = sprintf('((%s) OR (%s))', $matchClause, implode(' OR ', $likeConditions));
        } else {
            $conditions[] = sprintf('(%s)', $matchClause);
        }

        return $conditions;
    }

    public static function bindSearchParameters(\PDOStatement $statement, string $searchTerm, bool $bindPrefix): void
    {
        $variants = self::getSearchVariants($searchTerm);

        foreach ($variants as $index => $variant) {
            $statement->bindValue(':search_fulltext_' . $index, $variant, \PDO::PARAM_STR);
        }

        if ($searchTerm === '') {
            return;
        }

        foreach ($variants as $index => $variant) {
            $statement->bindValue(
                ':search_like_' . $index,
                self::buildSearchLikeParameter($variant),
                \PDO::PARAM_STR
            );
        }

        if ($bindPrefix) {
            foreach ($variants as $index => $variant) {
                $statement->bindValue(
                    ':search_prefix_' . $index,
                    self::buildSearchPrefixParameter($variant),
                    \PDO::PARAM_STR
                );
            }
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

    /**
     * @return list<string>
     */
    private static function getSearchVariants(string $searchTerm): array
    {
        $variants = [$searchTerm];

        $romanVariant = self::replaceDigitsWithRomans($searchTerm);
        if ($romanVariant !== $searchTerm) {
            $variants[] = $romanVariant;
        }

        $numericVariant = self::replaceRomansWithDigits($searchTerm);
        if ($numericVariant !== $searchTerm && !in_array($numericVariant, $variants, true)) {
            $variants[] = $numericVariant;
        }

        return $variants;
    }

    private static function replaceDigitsWithRomans(string $value): string
    {
        return (string) preg_replace_callback(
            '/\b\d+\b/u',
            static function (array $matches): string {
                $number = (int) $matches[0];
                $roman = self::convertIntToRoman($number);

                return $roman ?? $matches[0];
            },
            $value
        );
    }

    private static function replaceRomansWithDigits(string $value): string
    {
        return (string) preg_replace_callback(
            '/\b[ivxlcdm]+\b/ui',
            static function (array $matches): string {
                $roman = strtoupper($matches[0]);
                $number = self::convertRomanToInt($roman);

                if ($number === null) {
                    return $matches[0];
                }

                $normalizedRoman = self::convertIntToRoman($number);
                if ($normalizedRoman !== $roman) {
                    return $matches[0];
                }

                return (string) $number;
            },
            $value
        );
    }

    private static function convertIntToRoman(int $number): ?string
    {
        if ($number <= 0 || $number >= 4000) {
            return null;
        }

        $map = [
            'M' => 1000,
            'CM' => 900,
            'D' => 500,
            'CD' => 400,
            'C' => 100,
            'XC' => 90,
            'L' => 50,
            'XL' => 40,
            'X' => 10,
            'IX' => 9,
            'V' => 5,
            'IV' => 4,
            'I' => 1,
        ];

        $result = '';
        foreach ($map as $roman => $value) {
            while ($number >= $value) {
                $result .= $roman;
                $number -= $value;
            }
        }

        return $result;
    }

    private static function convertRomanToInt(string $roman): ?int
    {
        if ($roman === '') {
            return null;
        }

        $map = [
            'M' => 1000,
            'D' => 500,
            'C' => 100,
            'L' => 50,
            'X' => 10,
            'V' => 5,
            'I' => 1,
        ];

        $length = strlen($roman);
        $total = 0;
        $previousValue = 0;

        for ($i = $length - 1; $i >= 0; $i--) {
            $char = $roman[$i];
            $value = $map[$char] ?? null;

            if ($value === null) {
                return null;
            }

            if ($value < $previousValue) {
                $total -= $value;
            } else {
                $total += $value;
                $previousValue = $value;
            }
        }

        if ($total <= 0 || $total >= 4000) {
            return null;
        }

        return $total;
    }
}
