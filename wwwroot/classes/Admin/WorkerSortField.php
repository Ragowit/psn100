<?php

declare(strict_types=1);

enum WorkerSortField: string
{
    case Id = 'id';
    case ScanStart = 'scan_start';

    public static function fromMixed(mixed $value): self
    {
        if (!is_string($value)) {
            return self::ScanStart;
        }

        return self::tryFrom(strtolower(trim($value))) ?? self::ScanStart;
    }

    public function toSqlColumn(): string
    {
        return $this->value;
    }
}
