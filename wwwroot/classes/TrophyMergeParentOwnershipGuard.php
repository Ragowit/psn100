<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyMergeMetadataRepository.php';

/**
 * Enforces that a child trophy title may belong to at most one merge parent.
 */
final readonly class TrophyMergeParentOwnershipGuard
{
    public function __construct(
        final private PDO $database,
        final private TrophyMergeMetadataRepository $metadataRepository
    ) {
    }

    /**
     * Best-effort unlocked check for early rejection before opening a write transaction.
     */
    public function assertChildCanUseParent(string $childNpCommunicationId, string $parentNpCommunicationId): void
    {
        $this->assertChildOwnership(
            $childNpCommunicationId,
            $parentNpCommunicationId,
            $this->readMetaParent($childNpCommunicationId),
            currentReadMergeParents: false
        );
    }

    /**
     * Lock child meta rows in sorted order, then enforce the single-parent rule using
     * current-read values from the locks (safe under MySQL REPEATABLE READ).
     *
     * @param list<string> $childNpCommunicationIds
     */
    public function lockAndAssertChildrenCanUseParent(
        array $childNpCommunicationIds,
        string $parentNpCommunicationId
    ): void {
        $uniqueChildNpCommunicationIds = $childNpCommunicationIds
            |> array_unique(...)
            |> array_values(...);
        sort($uniqueChildNpCommunicationIds, SORT_STRING);

        foreach ($uniqueChildNpCommunicationIds as $childNpCommunicationId) {
            $lockedMetaParent = $this->metadataRepository->lockChildMetaForParentAssignment(
                $childNpCommunicationId
            );
            $this->assertChildOwnership(
                $childNpCommunicationId,
                $parentNpCommunicationId,
                $lockedMetaParent,
                currentReadMergeParents: true
            );
        }
    }

    private function assertChildOwnership(
        string $childNpCommunicationId,
        string $parentNpCommunicationId,
        ?string $metaParent,
        bool $currentReadMergeParents
    ): void {
        $mergeParents = $this->findParentsFromTrophyMerge(
            $childNpCommunicationId,
            forUpdate: $currentReadMergeParents
                && $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite'
        );

        if (count($mergeParents) > 1) {
            throw new InvalidArgumentException(
                'A child game can only have one parent. This game has conflicting merge mappings to '
                . implode(', ', $mergeParents)
                . ' and must be repaired before continuing.'
            );
        }

        $mergeParent = $mergeParents === [] ? null : array_first($mergeParents);

        if (
            $metaParent !== null
            && $mergeParent !== null
            && $metaParent !== $mergeParent
        ) {
            throw new InvalidArgumentException(
                'A child game can only have one parent. This game has conflicting parents '
                . $metaParent
                . ' and '
                . $mergeParent
                . ' and must be repaired before continuing.'
            );
        }

        $existingParent = $metaParent ?? $mergeParent;

        if ($existingParent === null || $existingParent === $parentNpCommunicationId) {
            return;
        }

        throw new InvalidArgumentException(
            'A child game can only have one parent. This game is already merged into '
            . $existingParent
            . '.'
        );
    }

    private function readMetaParent(string $childNpCommunicationId): ?string
    {
        $metaQuery = $this->database->prepare(
            <<<'SQL'
            SELECT parent_np_communication_id
            FROM trophy_title_meta
            WHERE np_communication_id = :np_communication_id
            SQL
        );
        $metaQuery->bindValue(':np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $metaQuery->execute();

        $parentFromMeta = $metaQuery->fetchColumn();
        if ($parentFromMeta === false || $parentFromMeta === null || $parentFromMeta === '') {
            return null;
        }

        return (string) $parentFromMeta;
    }

    /**
     * @return list<string>
     */
    private function findParentsFromTrophyMerge(string $childNpCommunicationId, bool $forUpdate): array
    {
        $sql = <<<'SQL'
            SELECT DISTINCT parent_np_communication_id
            FROM trophy_merge
            WHERE child_np_communication_id = :np_communication_id
            ORDER BY parent_np_communication_id
            SQL;

        if ($forUpdate) {
            $sql .= "\nFOR UPDATE";
        }

        $mergeQuery = $this->database->prepare($sql);
        $mergeQuery->bindValue(':np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $mergeQuery->execute();

        /** @var list<string> $parents */
        $parents = $mergeQuery->fetchAll(PDO::FETCH_COLUMN);

        return $parents;
    }
}
