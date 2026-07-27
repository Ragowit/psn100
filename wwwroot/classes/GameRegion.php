<?php

declare(strict_types=1);

enum GameRegion: string
{
    case Na = 'NA';
    case Eu = 'EU';
    case Hk = 'HK';
    case Jp = 'JP';
    case As = 'AS';

    #[\NoDiscard]
    public function sortRank(): int
    {
        return match ($this) {
            self::Na => 0,
            self::Eu => 1,
            self::Hk => 3,
            self::Jp => 4,
            self::As => 5,
        };
    }

    /**
     * SQL CASE expression that ranks known regions, NULL, then everything else.
     */
    #[\NoDiscard]
    public static function sqlSortCaseExpression(string $column = 'region'): string
    {
        $lines = ['CASE'];

        foreach ([self::Na, self::Eu] as $region) {
            $lines[] = sprintf(
                "    WHEN %s = '%s' THEN %d",
                $column,
                $region->value,
                $region->sortRank()
            );
        }

        $lines[] = sprintf('    WHEN %s IS NULL THEN 2', $column);

        foreach ([self::Hk, self::Jp, self::As] as $region) {
            $lines[] = sprintf(
                "    WHEN %s = '%s' THEN %d",
                $column,
                $region->value,
                $region->sortRank()
            );
        }

        $lines[] = '    ELSE 6';
        $lines[] = 'END';

        return implode("\n", $lines);
    }
}
