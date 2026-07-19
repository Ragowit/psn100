<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerScanTrophySummaryAccessStatus.php';

/**
 * Outcome of reading a PSN user's trophy summary level during a player scan.
 */
final readonly class PlayerScanTrophySummaryAccessResult
{
    private function __construct(
        final public PlayerScanTrophySummaryAccessStatus $status,
        final public int $level = 0,
    ) {
    }

    #[\NoDiscard]
    public static function accessible(int $level): self
    {
        return new self(PlayerScanTrophySummaryAccessStatus::Accessible, $level);
    }

    #[\NoDiscard]
    public static function privateProfile(): self
    {
        return new self(PlayerScanTrophySummaryAccessStatus::Private);
    }

    #[\NoDiscard]
    public static function abortScan(): self
    {
        return new self(PlayerScanTrophySummaryAccessStatus::AbortScan);
    }

    public function isAccessible(): bool
    {
        return $this->status === PlayerScanTrophySummaryAccessStatus::Accessible;
    }

    public function isPrivateProfile(): bool
    {
        return $this->status === PlayerScanTrophySummaryAccessStatus::Private;
    }

    public function shouldAbortScan(): bool
    {
        return $this->status === PlayerScanTrophySummaryAccessStatus::AbortScan;
    }
}
