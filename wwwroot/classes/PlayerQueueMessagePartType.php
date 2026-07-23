<?php

declare(strict_types=1);

enum PlayerQueueMessagePartType: string
{
    case Text = 'text';
    case Link = 'link';
    case Emphasis = 'emphasis';
    case Spinner = 'spinner';
    case Progress = 'progress';

    #[\NoDiscard]
    public static function tryFromMixed(mixed $value): ?self
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        return self::tryFrom(((string) $value) |> trim(...) |> strtolower(...));
    }
}
