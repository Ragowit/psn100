<?php

declare(strict_types=1);

enum GameDetailAction: string
{
    case UpdateStatus = 'update-status';
    case UpdateDetail = 'update-detail';

    #[\NoDiscard]
    public static function tryFromMixed(mixed $value): ?self
    {
        if (!is_string($value)) {
            return null;
        }

        return self::tryFrom($value |> trim(...) |> strtolower(...));
    }
}
