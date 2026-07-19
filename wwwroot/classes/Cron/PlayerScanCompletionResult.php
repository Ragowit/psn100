<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerScanCompletionStatus.php';

/**
 * Outcome of post-scan player aggregation during a worker scan.
 */
final class PlayerScanCompletionResult
{
    private function __construct(final public readonly PlayerScanCompletionStatus $status)
    {
    }

    #[\NoDiscard]
    public static function completed(): self
    {
        return new self(PlayerScanCompletionStatus::Completed);
    }

    #[\NoDiscard]
    public static function continueScan(): self
    {
        return new self(PlayerScanCompletionStatus::ContinueScan);
    }

    public function shouldContinueScan(): bool
    {
        return $this->status === PlayerScanCompletionStatus::ContinueScan;
    }
}
