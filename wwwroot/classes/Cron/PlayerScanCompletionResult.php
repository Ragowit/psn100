<?php

declare(strict_types=1);

/**
 * Outcome of post-scan player aggregation during a worker scan.
 */
final class PlayerScanCompletionResult
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CONTINUE_SCAN = 'continue_scan';

    private function __construct(public readonly string $status)
    {
    }

    #[\NoDiscard]
    public static function completed(): self
    {
        return new self(self::STATUS_COMPLETED);
    }

    #[\NoDiscard]
    public static function continueScan(): self
    {
        return new self(self::STATUS_CONTINUE_SCAN);
    }

    public function shouldContinueScan(): bool
    {
        return $this->status === self::STATUS_CONTINUE_SCAN;
    }
}
