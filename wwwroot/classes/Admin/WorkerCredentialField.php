<?php

declare(strict_types=1);

enum WorkerCredentialField: string
{
    case RefreshToken = 'refresh_token';
    case Npsso = 'npsso';

    public static function fromMixed(mixed $value): ?self
    {
        if (!is_string($value)) {
            return null;
        }

        return self::tryFrom($value);
    }
}
