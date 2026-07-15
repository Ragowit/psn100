<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyGroupConflictResolver.php';

/**
 * Copies trophy groups from a child title into a MERGE parent during admin copy.
 */
final class MergeTrophyGroupCopier
{
    private const TROPHY_GROUP_UPDATE_QUERY = <<<'SQL'
        WITH
            tg_org AS(
            SELECT
                group_id,
                name,
                detail,
                icon_url
            FROM
                trophy_group
            WHERE
                np_communication_id = :child_np_communication_id
        )
        UPDATE
            trophy_group tg
            INNER JOIN tg_org ON tg.group_id = tg_org.group_id
        SET
            tg.name = tg_org.name,
            tg.detail = tg_org.detail,
            tg.icon_url = tg_org.icon_url
        WHERE
            tg.np_communication_id = :parent_np_communication_id
        SQL;

    private const TROPHY_GROUP_INSERT_QUERY = <<<'SQL'
        INSERT INTO
            trophy_group (
                np_communication_id,
                group_id,
                name,
                detail,
                icon_url,
                bronze,
                silver,
                gold,
                platinum
            )
        SELECT
            :parent_np_communication_id,
            tg.group_id,
            tg.name,
            tg.detail,
            tg.icon_url,
            tg.bronze,
            tg.silver,
            tg.gold,
            tg.platinum
        FROM
            trophy_group tg
        WHERE
            tg.np_communication_id = :child_np_communication_id
            AND NOT EXISTS (
                SELECT
                    1
                FROM
                    trophy_group existing
                WHERE
                    existing.np_communication_id = :parent_np_communication_id
                    AND existing.group_id = tg.group_id
            )
        SQL;

    public function __construct(
        private readonly PDO $database,
        private readonly TrophyGroupConflictResolver $groupConflictResolver = new TrophyGroupConflictResolver()
    ) {
    }

    public function copyTrophyGroups(string $childNpCommunicationId, string $parentNpCommunicationId): void
    {
        $query = $this->database->prepare(self::TROPHY_GROUP_UPDATE_QUERY);
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }

    public function copyNewTrophyGroups(string $childNpCommunicationId, string $parentNpCommunicationId): void
    {
        $query = $this->database->prepare(self::TROPHY_GROUP_INSERT_QUERY);
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }

    /**
     * @param string[] $forcedGroupIds
     * @return array<string, string>
     */
    public function copyConflictingTrophyGroups(
        string $childNpCommunicationId,
        string $parentNpCommunicationId,
        array $forcedGroupIds = [],
        bool $preserveGroupIds = false
    ): array {
        $conflictingGroupIds = $this->getConflictingGroupIds($childNpCommunicationId, $parentNpCommunicationId);

        if ($forcedGroupIds !== []) {
            foreach ($forcedGroupIds as $groupId) {
                if ($this->groupConflictResolver->parseNumericGroupId($groupId) === null) {
                    continue;
                }

                if (!in_array($groupId, $conflictingGroupIds, true)) {
                    $conflictingGroupIds[] = $groupId;
                }
            }
        }

        if ($conflictingGroupIds === []) {
            return [];
        }

        $existingGroupIds = $this->getExistingGroupIds($parentNpCommunicationId);
        $existingGroupMappings = $this->getExistingGroupMappings($childNpCommunicationId, $parentNpCommunicationId);
        $groupIdMapping = $existingGroupMappings;

        $childGroupTrophyNames = $this->getTrophyNamesByGroup($childNpCommunicationId, $conflictingGroupIds);
        $parentGroupTrophyNames = $this->getTrophyNamesByGroup($parentNpCommunicationId, null);

        $usedParentGroups = [];
        foreach ($existingGroupMappings as $parentGroupId) {
            $usedParentGroups[(string) $parentGroupId] = true;
        }

        $groupOffset = $this->groupConflictResolver->determineGroupOffset($existingGroupIds);
        $preferredOffset = $this->groupConflictResolver->determinePreferredGroupOffset($existingGroupMappings);

        $select = $this->database->prepare(
            'SELECT group_id, name, detail, icon_url, bronze, silver, gold, platinum
             FROM   trophy_group
             WHERE  np_communication_id = :np_communication_id
             AND    group_id = :group_id'
        );
        $upsert = $this->database->prepare(
            'INSERT INTO trophy_group (np_communication_id, group_id, name, detail, icon_url, bronze, silver, gold, platinum)
             VALUES (:np_communication_id, :group_id, :name, :detail, :icon_url, :bronze, :silver, :gold, :platinum)
             AS new
             ON DUPLICATE KEY UPDATE
                 name = new.name,
                 detail = new.detail,
                 icon_url = new.icon_url,
                 bronze = new.bronze,
                 silver = new.silver,
                 gold = new.gold,
                 platinum = new.platinum'
        );

        foreach ($conflictingGroupIds as $groupId) {
            $select->bindValue(':np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
            $select->bindValue(':group_id', $groupId, PDO::PARAM_STR);
            $select->execute();

            $group = $select->fetch(PDO::FETCH_ASSOC);

            if ($group === false) {
                $select->closeCursor();
                continue;
            }

            if ($preserveGroupIds) {
                $targetGroupId = $groupIdMapping[$groupId] ?? (string) $group['group_id'];

                $this->upsertTrophyGroup(
                    $upsert,
                    $parentNpCommunicationId,
                    $targetGroupId,
                    $group
                );

                $groupIdMapping[$groupId] = $targetGroupId;
                $existingGroupIds[$targetGroupId] = true;
                $usedParentGroups[(string) $targetGroupId] = true;
                $parentGroupTrophyNames[$targetGroupId] = $childGroupTrophyNames[$groupId] ?? [];
                $select->closeCursor();
                continue;
            }

            $numericGroupId = $this->groupConflictResolver->parseNumericGroupId($groupId);

            if ($numericGroupId === null) {
                $select->closeCursor();
                continue;
            }

            $targetGroupId = $groupIdMapping[$groupId] ?? null;

            if ($targetGroupId === null) {
                $targetGroupId = $this->groupConflictResolver->findMatchingParentGroupId(
                    $groupId,
                    $childGroupTrophyNames,
                    $parentGroupTrophyNames,
                    $usedParentGroups
                );
            }

            if ($targetGroupId === null) {
                $allocation = $this->groupConflictResolver->allocateNonConflictingGroupId(
                    $numericGroupId,
                    (string) $group['group_id'],
                    $existingGroupIds,
                    $preferredOffset,
                    $groupOffset
                );

                $preferredOffset = $allocation['preferredOffset'];
                $groupOffset = $allocation['groupOffset'];
                $targetGroupId = $allocation['groupId'];
            }

            $this->upsertTrophyGroup(
                $upsert,
                $parentNpCommunicationId,
                $targetGroupId,
                $group
            );

            $groupIdMapping[$groupId] = $targetGroupId;
            $existingGroupIds[$targetGroupId] = true;
            $usedParentGroups[(string) $targetGroupId] = true;
            $parentGroupTrophyNames[$targetGroupId] = $childGroupTrophyNames[$groupId] ?? [];

            $select->closeCursor();
        }

        return $groupIdMapping;
    }

    /**
     * @return string[]
     */
    private function getConflictingGroupIds(string $childNpCommunicationId, string $parentNpCommunicationId): array
    {
        $query = $this->database->prepare(
            'SELECT child.group_id
             FROM   trophy_group child
                    INNER JOIN trophy_group parent ON parent.np_communication_id = :parent_np_communication_id
                                                 AND parent.group_id = child.group_id
             WHERE  child.np_communication_id = :child_np_communication_id
             ORDER BY child.group_id'
        );
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $groupIds = [];

        while (($groupId = $query->fetchColumn()) !== false) {
            $groupId = (string) $groupId;

            if ($this->groupConflictResolver->parseNumericGroupId($groupId) === null) {
                continue;
            }

            $groupIds[] = $groupId;
        }

        return $groupIds;
    }

    /**
     * @return array<string, bool>
     */
    private function getExistingGroupIds(string $npCommunicationId): array
    {
        $query = $this->database->prepare('SELECT group_id FROM trophy_group WHERE np_communication_id = :np_communication_id');
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $groupIds = [];

        while (($groupId = $query->fetchColumn()) !== false) {
            $groupIds[(string) $groupId] = true;
        }

        return $groupIds;
    }

    /**
     * @return array<string, string>
     */
    private function getExistingGroupMappings(string $childNpCommunicationId, string $parentNpCommunicationId): array
    {
        $query = $this->database->prepare(
            'SELECT child_group_id, parent_group_id, parent_order_id
             FROM   trophy_merge
             WHERE  child_np_communication_id = :child_np_communication_id
             AND    parent_np_communication_id = :parent_np_communication_id
             ORDER BY child_group_id, parent_order_id DESC'
        );
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $mappings = [];

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $childGroupId = (string) $row['child_group_id'];
            $parentGroupId = (string) $row['parent_group_id'];

            if ($parentGroupId === '') {
                continue;
            }

            if (!isset($mappings[$childGroupId])) {
                $mappings[$childGroupId] = $parentGroupId;
            }
        }

        $query->closeCursor();

        return $mappings;
    }

    /**
     * @param string[]|null $groupIds
     * @return array<string, string[]>
     */
    private function getTrophyNamesByGroup(string $npCommunicationId, ?array $groupIds): array
    {
        if ($groupIds !== null && $groupIds === []) {
            return [];
        }

        $sql = 'SELECT group_id, name FROM trophy WHERE np_communication_id = ?';
        $parameters = [$npCommunicationId];

        if ($groupIds !== null) {
            $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
            $sql .= ' AND group_id IN (' . $placeholders . ')';

            foreach ($groupIds as $groupId) {
                $parameters[] = $groupId;
            }
        }

        $sql .= ' ORDER BY group_id, name';

        $query = $this->database->prepare($sql);
        $query->execute($parameters);

        $names = [];

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $groupId = (string) $row['group_id'];
            $names[$groupId][] = (string) $row['name'];
        }

        $query->closeCursor();

        foreach ($names as &$groupNames) {
            sort($groupNames, SORT_STRING);
        }
        unset($groupNames);

        return $names;
    }

    private function upsertTrophyGroup(PDOStatement $statement, string $parentNpCommunicationId, string $groupId, array $group): void
    {
        $statement->execute([
            ':np_communication_id' => $parentNpCommunicationId,
            ':group_id' => $groupId,
            ':name' => (string) $group['name'],
            ':detail' => (string) $group['detail'],
            ':icon_url' => (string) $group['icon_url'],
            ':bronze' => (int) $group['bronze'],
            ':silver' => (int) $group['silver'],
            ':gold' => (int) $group['gold'],
            ':platinum' => (int) $group['platinum'],
        ]);

        $statement->closeCursor();
    }
}
