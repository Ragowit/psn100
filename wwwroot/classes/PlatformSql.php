<?php

declare(strict_types=1);

/**
 * Shared SQL fragments for filtering trophy_title.platform.
 *
 * Platform values are comma-separated (often with spaces). PSVR must not match
 * PSVR2, so its predicate tokenizes after stripping spaces rather than using a
 * plain LIKE / REGEXP against the raw column.
 */
final class PlatformSql
{
    public const string PSVR_TOKEN_MATCH =
        "CONCAT(',', REPLACE(tt.platform, ' ', ''), ',') LIKE '%,PSVR,%'";

    /**
     * @var array<string, string>
     */
    public const array CONDITIONS = [
        'pc' => "tt.platform LIKE '%PC%'",
        'ps3' => "tt.platform LIKE '%PS3%'",
        'ps4' => "tt.platform LIKE '%PS4%'",
        'ps5' => "tt.platform LIKE '%PS5%'",
        'psvita' => "tt.platform LIKE '%PSVITA%'",
        'psvr' => self::PSVR_TOKEN_MATCH,
        'psvr2' => "tt.platform LIKE '%PSVR2%'",
    ];

    /**
     * @param list<string> $platformKeys
     * @return non-empty-string|null Parenthesized OR expression, or null when empty.
     */
    public static function buildOrExpression(array $platformKeys): ?string
    {
        $clauses = [];

        foreach ($platformKeys as $platformKey) {
            $condition = self::CONDITIONS[$platformKey] ?? null;
            if ($condition !== null) {
                $clauses[] = $condition;
            }
        }

        if ($clauses === []) {
            return null;
        }

        return '(' . implode(' OR ', $clauses) . ')';
    }

    /**
     * @param list<string> $platformKeys
     */
    public static function buildOrClause(array $platformKeys): string
    {
        $expression = self::buildOrExpression($platformKeys);

        return $expression === null ? '' : ' AND ' . $expression;
    }

    public static function conditionFor(string $platformKey): ?string
    {
        return self::CONDITIONS[$platformKey] ?? null;
    }
}
