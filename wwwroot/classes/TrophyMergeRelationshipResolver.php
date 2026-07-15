<?php

declare(strict_types=1);

/**
 * Resolves parent/child relationships for merged trophy titles from trophy_merge and trophy_title_meta.
 */
final class TrophyMergeRelationshipResolver
{
    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @return array{parent_np_communication_id:string, child_np_communication_ids:list<string>}
     */
    public function getMergeParentAndChildren(string $childNpCommunicationId): array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT DISTINCT
                parent_np_communication_id
            FROM
                trophy_merge
            WHERE
                child_np_communication_id = :child_np_communication_id
SQL
        );
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->execute();

        /** @var list<string> $parentIds */
        $parentIds = $query->fetchAll(PDO::FETCH_COLUMN);

        if ($parentIds === []) {
            throw new RuntimeException('Unable to locate parent trophy title.');
        }

        if (count($parentIds) === 1) {
            $parentNpCommunicationId = array_first($parentIds);
        } else {
            $parentNpCommunicationId = $this->resolveMergeParent($childNpCommunicationId, $parentIds);
        }

        return [
            'parent_np_communication_id' => $parentNpCommunicationId,
            'child_np_communication_ids' => $this->getMergeChildrenByParent($parentNpCommunicationId),
        ];
    }

    /**
     * @return list<string>
     */
    public function getMergeChildrenByParent(string $parentNpCommunicationId): array
    {
        $childQuery = $this->database->prepare(
            <<<'SQL'
            SELECT DISTINCT
                child_np_communication_id
            FROM
                trophy_merge
            WHERE
                parent_np_communication_id = :parent_np_communication_id
            ORDER BY
                child_np_communication_id
SQL
        );
        $childQuery->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $childQuery->execute();

        /** @var list<string> $childNpCommunicationIds */
        $childNpCommunicationIds = $childQuery->fetchAll(PDO::FETCH_COLUMN);

        return $childNpCommunicationIds;
    }

    /**
     * @param list<string> $parentIds
     */
    private function resolveMergeParent(string $childNpCommunicationId, array $parentIds): string
    {
        $parentFromMeta = $this->getParentFromMeta($childNpCommunicationId);
        if ($parentFromMeta !== null && in_array($parentFromMeta, $parentIds, true)) {
            return $parentFromMeta;
        }

        sort($parentIds, SORT_STRING);

        return array_first($parentIds);
    }

    private function getParentFromMeta(string $childNpCommunicationId): ?string
    {
        $query = $this->database->prepare(
            'SELECT parent_np_communication_id FROM trophy_title_meta WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $parent = $query->fetchColumn();
        if ($parent === false || $parent === null || $parent === '') {
            return null;
        }

        return (string) $parent;
    }
}
