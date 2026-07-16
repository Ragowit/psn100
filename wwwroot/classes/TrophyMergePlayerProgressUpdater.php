<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyMergeRelationshipResolver.php';
require_once __DIR__ . '/TrophyMergePlayerProgressRecalculator.php';

/**
 * Orchestrates player progress updates for merged trophy titles.
 *
 * Resolves parent/child relationships, then delegates aggregate recalculation
 * to TrophyMergePlayerProgressRecalculator.
 */
class TrophyMergePlayerProgressUpdater
{
    private readonly TrophyMergeRelationshipResolver $relationshipResolver;
    private readonly TrophyMergePlayerProgressRecalculator $progressRecalculator;

    public function __construct(
        private readonly PDO $database,
        ?TrophyMergeRelationshipResolver $relationshipResolver = null,
        ?TrophyMergePlayerProgressRecalculator $progressRecalculator = null,
    ) {
        $this->relationshipResolver = $relationshipResolver ?? new TrophyMergeRelationshipResolver($database);
        $this->progressRecalculator = $progressRecalculator ?? new TrophyMergePlayerProgressRecalculator($database);
    }

    public function updateTrophyGroupPlayer(int $childGameId): void
    {
        $childNpCommunicationId = $this->getGameNpCommunicationId($childGameId);

        $mergeData = $this->getMergeParentAndChildren($childNpCommunicationId);
        $parentNpCommunicationId = $mergeData['parent_np_communication_id'];
        $childNpCommunicationIds = array_values($mergeData['child_np_communication_ids']);

        $this->progressRecalculator->recalculateGroupPlayer($parentNpCommunicationId, $childNpCommunicationIds);
    }

    public function updateTrophyTitlePlayer(int $childGameId): void
    {
        $childNpCommunicationId = $this->getGameNpCommunicationId($childGameId);

        $mergeData = $this->getMergeParentAndChildren($childNpCommunicationId);
        $parentNpCommunicationId = $mergeData['parent_np_communication_id'];
        $childNpCommunicationIds = array_values($mergeData['child_np_communication_ids']);

        $this->progressRecalculator->recalculateTitlePlayer($parentNpCommunicationId, $childNpCommunicationIds);
    }

    public function recomputeByParent(string $parentNpCommunicationId): void
    {
        if (!str_starts_with($parentNpCommunicationId, 'MERGE')) {
            throw new InvalidArgumentException('Parent must be a merge title.');
        }

        $childNpCommunicationIds = $this->relationshipResolver->getMergeChildrenByParent($parentNpCommunicationId);

        if ($childNpCommunicationIds === []) {
            throw new RuntimeException('Unable to locate child trophy titles.');
        }

        $this->progressRecalculator->recalculateGroupPlayer($parentNpCommunicationId, $childNpCommunicationIds);
        $this->progressRecalculator->recalculateTitlePlayer($parentNpCommunicationId, $childNpCommunicationIds);
    }

    /**
     * @return array{parent_np_communication_id:string, child_np_communication_ids:list<string>}
     */
    public function getMergeParentAndChildren(string $childNpCommunicationId): array
    {
        return $this->relationshipResolver->getMergeParentAndChildren($childNpCommunicationId);
    }

    private function getGameNpCommunicationId(int $gameId): string
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT np_communication_id
            FROM   trophy_title
            WHERE  id = :id
SQL
        );
        $query->bindValue(':id', $gameId, PDO::PARAM_INT);
        $query->execute();

        $npCommunicationId = $query->fetchColumn();

        if ($npCommunicationId === false) {
            throw new InvalidArgumentException('Game not found.');
        }

        return (string) $npCommunicationId;
    }
}
