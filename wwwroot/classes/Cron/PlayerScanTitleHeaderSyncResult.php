<?php

declare(strict_types=1);

/**
 * Outcome of synchronizing a trophy title header row during player scans.
 */
final readonly class PlayerScanTitleHeaderSyncResult
{
    public function __construct(
        final public bool $titleDataChanged,
        final public bool $titleNeedsUpdate,
        final public bool $isNewTitle,
    ) {
    }
}
