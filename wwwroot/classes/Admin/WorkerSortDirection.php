<?php

declare(strict_types=1);

enum WorkerSortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    public static function fromMixed(mixed $value): self
    {
        if (!is_string($value)) {
            return self::Asc;
        }

        return self::tryFrom(strtolower(trim($value))) ?? self::Asc;
    }

    public function toSqlKeyword(): string
    {
        return strtoupper($this->value);
    }

    public function indicator(): string
    {
        return $this === self::Asc ? ' ▲' : ' ▼';
    }

    public function toggled(): self
    {
        return $this === self::Asc ? self::Desc : self::Asc;
    }
}
