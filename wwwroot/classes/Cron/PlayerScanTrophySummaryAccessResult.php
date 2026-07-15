<?php

declare(strict_types=1);

/**
 * Outcome of reading a PSN user's trophy summary level during a player scan.
 */
final class PlayerScanTrophySummaryAccessResult
{
    public const STATUS_ACCESSIBLE = 'accessible';
    public const STATUS_PRIVATE = 'private';
    public const STATUS_ABORT_SCAN = 'abort_scan';

    private function __construct(
        public readonly string $status,
        public readonly int $level = 0,
    ) {
    }

    #[\NoDiscard]
    public static function accessible(int $level): self
    {
        return new self(self::STATUS_ACCESSIBLE, $level);
    }

    #[\NoDiscard]
    public static function privateProfile(): self
    {
        return new self(self::STATUS_PRIVATE);
    }

    #[\NoDiscard]
    public static function abortScan(): self
    {
        return new self(self::STATUS_ABORT_SCAN);
    }

    public function isAccessible(): bool
    {
        return $this->status === self::STATUS_ACCESSIBLE;
    }

    public function isPrivateProfile(): bool
    {
        return $this->status === self::STATUS_PRIVATE;
    }

    public function shouldAbortScan(): bool
    {
        return $this->status === self::STATUS_ABORT_SCAN;
    }
}
