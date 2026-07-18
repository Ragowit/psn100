<?php

declare(strict_types=1);

/**
 * Outcome of synchronizing one trophy title's catalog rows during a player scan.
 */
final readonly class PlayerScanTitleCatalogSyncResult
{
    /**
     * @param list<string> $mergeParentsToRecompute
     */
    private function __construct(
        final public bool $restartScan,
        final public bool $newTrophies,
        final public bool $isNewTitle,
        final public ?int $titleId,
        final public array $mergeParentsToRecompute,
    ) {
    }

    #[\NoDiscard]
    public static function restartScan(): self
    {
        return new self(true, false, false, null, []);
    }

    /**
     * @param list<string> $mergeParentsToRecompute
     */
    #[\NoDiscard]
    public static function synced(
        bool $newTrophies,
        bool $isNewTitle,
        ?int $titleId,
        array $mergeParentsToRecompute,
    ): self {
        return new self(false, $newTrophies, $isNewTitle, $titleId, $mergeParentsToRecompute);
    }
}
