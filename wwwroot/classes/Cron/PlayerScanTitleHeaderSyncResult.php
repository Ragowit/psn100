<?php

declare(strict_types=1);

/**
 * Outcome of synchronizing a trophy title header row during player scans.
 */
final class PlayerScanTitleHeaderSyncResult
{
    public function __construct(
        public readonly bool $titleDataChanged,
        public readonly bool $titleNeedsUpdate,
        public readonly bool $isNewTitle,
    ) {
    }
}
