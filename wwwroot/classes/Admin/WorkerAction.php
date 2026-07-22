<?php

declare(strict_types=1);

enum WorkerAction: string
{
    case UpdateNpsso = 'update_npsso';
    case UpdateRefreshToken = 'update_refresh_token';
    case RestartWorker = 'restart_worker';
    case RestartAllWorkers = 'restart_all_workers';

    #[\NoDiscard]
    public static function tryFromMixed(mixed $value): ?self
    {
        if (!is_string($value)) {
            return null;
        }

        return self::tryFrom($value |> trim(...) |> strtolower(...));
    }
}
