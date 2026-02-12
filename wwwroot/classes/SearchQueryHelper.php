<?php

declare(strict_types=1);

final class SearchQueryHelper
{
    private const ROMAN_MIN = 1;
    private const ROMAN_MAX = 3999;
    private const ROMAN_CONVERSION_MAP = [
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
    private const ROMAN_VALUE_MAP = [
        'M' => 1000,
        'D' => 500,
        'C' => 100,
        'L' => 50,
        'X' => 10,
        'V' => 5,
        'I' => 1,
    ];
    /**
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    public function addFulltextSelectColumns(
        array $columns,
        string $column,
        bool $includeScore,
        string $searchTerm
    ): array {
        if (!$includeScore) {
            return $columns;
        }

        $variants = $this->getSearchVariants($searchTerm);

        if ($variants === []) {
            $columns[] = '0 AS exact_match';
        } else {
            $exactConditions = $this->buildPlaceholderConditions(
                $column,
                $variants,
                '%s = :search_fulltext_%d'
            );

            $columns[] = sprintf('(%s) AS exact_match', implode(' OR ', $exactConditions));
        }

        if ($searchTerm !== '' && $variants !== []) {
            $prefixConditions = $this->buildPlaceholderConditions(
                $column,
                $variants,
                '%s LIKE :search_prefix_%d'
            );

            $columns[] = sprintf('(%s) AS prefix_match', implode(' OR ', $prefixConditions));
        } else {
            $columns[] = '0 AS prefix_match';
        }

        if ($variants === []) {
            $columns[] = sprintf('MATCH(%s) AGAINST (:search_fulltext_0) AS score', $column);
        } else {
            $scoreExpressions = $this->buildPlaceholderConditions(
                $column,
                $variants,
                'MATCH(%s) AGAINST (:search_fulltext_%d)'
            );

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
    public function appendFulltextCondition(
        array $conditions,
        bool $shouldApply,
        string $column,
        string $searchTerm
    ): array {
        if (!$shouldApply) {
            return $conditions;
        }

        $variants = $this->getSearchVariants($searchTerm);

        if ($variants === []) {
            return $conditions;
        }

        $matchConditions = $this->buildPlaceholderConditions(
            $column,
            $variants,
            '(MATCH(%s) AGAINST (:search_fulltext_%d)) > 0'
        );

        $matchClause = implode(' OR ', $matchConditions);

        if ($searchTerm !== '') {
            $likeConditions = $this->buildPlaceholderConditions(
                $column,
                $variants,
                '%s LIKE :search_like_%d'
            );

            $conditions[] = sprintf('((%s) OR (%s))', $matchClause, implode(' OR ', $likeConditions));
        } else {
            $conditions[] = sprintf('(%s)', $matchClause);
        }

        return $conditions;
    }

    public function bindSearchParameters(\PDOStatement $statement, string $searchTerm, bool $bindPrefix): void
    {
        $variants = $this->getSearchVariants($searchTerm);

        foreach ($variants as $index => $variant) {
            $statement->bindValue(':search_fulltext_' . $index, $variant, \PDO::PARAM_STR);
        }

        if ($searchTerm === '') {
            return;
        }

        foreach ($variants as $index => $variant) {
            $statement->bindValue(
                ':search_like_' . $index,
                $this->buildSearchLikeParameter($variant),
                \PDO::PARAM_STR
            );
        }

        if ($bindPrefix) {
            foreach ($variants as $index => $variant) {
                $statement->bindValue(
                    ':search_prefix_' . $index,
                    $this->buildSearchPrefixParameter($variant),
                    \PDO::PARAM_STR
                );
            }
        }
    }

    private function buildSearchLikeParameter(string $search): string
    {
        return '%' . addcslashes($search, "\\%_") . '%';
    }

    private function buildSearchPrefixParameter(string $search): string
    {
        return addcslashes($search, "\\%_") . '%';
    }

    /**
     * @param list<string> $variants
     * @return list<string>
     */
    private function buildPlaceholderConditions(string $column, array $variants, string $pattern): array
    {
        $conditions = [];

        foreach ($variants as $index => $_variant) {
            $conditions[] = sprintf($pattern, $column, $index);
        }

        return $conditions;
    }

    /**
     * @return list<string>
     */
    private function getSearchVariants(string $searchTerm): array
    {
        $variants = [$searchTerm => true];

        $romanVariant = $this->replaceDigitsWithRomans($searchTerm);
        if ($romanVariant !== $searchTerm) {
            $variants[$romanVariant] = true;
        }

        $numericVariant = $this->replaceRomansWithDigits($searchTerm);
        if ($numericVariant !== $searchTerm) {
            $variants[$numericVariant] = true;
        }

        return array_keys($variants);
    }

    private function replaceDigitsWithRomans(string $value): string
    {
        return (string) preg_replace_callback(
            '/\b\d+\b/u',
            function (array $matches): string {
                $number = (int) $matches[0];
                $roman = $this->convertIntToRoman($number);

                return $roman ?? $matches[0];
            },
            $value
        );
    }

    private function replaceRomansWithDigits(string $value): string
    {
        return (string) preg_replace_callback(
            '/\b[ivxlcdm]+\b/ui',
            function (array $matches): string {
                $roman = strtoupper($matches[0]);
                $number = $this->convertRomanToInt($roman);

                if ($number === null) {
                    return $matches[0];
                }

                $normalizedRoman = $this->convertIntToRoman($number);
                if ($normalizedRoman !== $roman) {
                    return $matches[0];
                }

                return (string) $number;
            },
            $value
        );
    }

    private function convertIntToRoman(int $number): ?string
    {
        if ($number < self::ROMAN_MIN || $number > self::ROMAN_MAX) {
            return null;
        }

        $result = '';
        foreach (self::ROMAN_CONVERSION_MAP as $roman => $value) {
            while ($number >= $value) {
                $result .= $roman;
                $number -= $value;
            }
        }

        return $result;
    }

    private function convertRomanToInt(string $roman): ?int
    {
        if ($roman === '') {
            return null;
        }

        $length = strlen($roman);
        $total = 0;
        $previousValue = 0;

        for ($i = $length - 1; $i >= 0; --$i) {
            $char = $roman[$i];
            $value = self::ROMAN_VALUE_MAP[$char] ?? null;

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

        if ($total < self::ROMAN_MIN || $total > self::ROMAN_MAX) {
            return null;
        }

        return $total;
    }
}
