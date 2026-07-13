<?php

declare(strict_types=1);

/**
 * Triggers automatic trophy-title merges when a new title appears during player scans.
 */
interface PlayerScanNewTitleMergeHandler
{
    /**
     * @return list<string> Merge parent NP communication IDs to recompute.
     */
    public function handleNewTitle(string $npCommunicationId): array;
}
