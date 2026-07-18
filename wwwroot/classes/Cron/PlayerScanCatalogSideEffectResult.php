<?php

declare(strict_types=1);

/**
 * Outcome of post-catalog side effects during a player scan.
 */
final readonly class PlayerScanCatalogSideEffectResult
{
    /**
     * @param list<string> $mergeParentsToRecompute
     */
    private function __construct(
        final public ?int $titleId,
        final public array $mergeParentsToRecompute,
    ) {
    }

    /**
     * @param list<string> $mergeParentsToRecompute
     */
    #[\NoDiscard]
    public static function create(?int $titleId, array $mergeParentsToRecompute): self
    {
        return new self($titleId, $mergeParentsToRecompute);
    }
}
