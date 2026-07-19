<?php

declare(strict_types=1);

enum WorkerSortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    #[\NoDiscard]
    public static function fromMixed(mixed $value): self
    {
        if (!is_string($value)) {
            return self::Asc;
        }

        return self::tryFrom($value |> trim(...) |> strtolower(...)) ?? self::Asc;
    }

    public function toSqlKeyword(): string
    {
        return strtoupper($this->value);
    }

    public function indicator(): string
    {
        return $this === self::Asc ? ' ▲' : ' ▼';
    }

    #[\NoDiscard]
    public function toggled(): self
    {
        return $this === self::Asc ? self::Desc : self::Asc;
    }
}
