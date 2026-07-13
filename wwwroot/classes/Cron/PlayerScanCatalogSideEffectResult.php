<?php

declare(strict_types=1);

/**
 * Outcome of post-catalog side effects during a player scan.
 */
final class PlayerScanCatalogSideEffectResult
{
    /**
     * @param list<string> $mergeParentsToRecompute
     */
    private function __construct(
        public readonly ?int $titleId,
        public readonly array $mergeParentsToRecompute,
    ) {
    }

    /**
     * @param list<string> $mergeParentsToRecompute
     */
    public static function create(?int $titleId, array $mergeParentsToRecompute): self
    {
        return new self($titleId, $mergeParentsToRecompute);
    }
}
