<?php

declare(strict_types=1);

/**
 * Outcome of synchronizing a trophy title header row during player scans.
 */
final readonly class PlayerScanTitleHeaderSyncResult
{
    public function __construct(
        public bool $titleDataChanged,
        public bool $titleNeedsUpdate,
        public bool $isNewTitle,
    ) {
    }
}
