<?php

declare(strict_types=1);

/**
 * Outcome of synchronizing one trophy title's catalog rows during a player scan.
 */
final class PlayerScanTitleCatalogSyncResult
{
    /**
     * @param list<string> $mergeParentsToRecompute
     */
    private function __construct(
        public readonly bool $restartScan,
        public readonly bool $newTrophies,
        public readonly bool $isNewTitle,
        public readonly ?int $titleId,
        public readonly array $mergeParentsToRecompute,
    ) {
    }

    public static function restartScan(): self
    {
        return new self(true, false, false, null, []);
    }

    /**
     * @param list<string> $mergeParentsToRecompute
     */
    public static function synced(
        bool $newTrophies,
        bool $isNewTitle,
        ?int $titleId,
        array $mergeParentsToRecompute,
    ): self {
        return new self(false, $newTrophies, $isNewTitle, $titleId, $mergeParentsToRecompute);
    }
}
