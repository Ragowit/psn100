<?php

declare(strict_types=1);

enum PossibleCheaterDateOperator: string
{
    case GreaterThanOrEqual = '>=';
    case LessThanOrEqual = '<=';
    case LessThan = '<';

    #[\NoDiscard]
    public static function tryFromMixed(mixed $value): ?self
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
