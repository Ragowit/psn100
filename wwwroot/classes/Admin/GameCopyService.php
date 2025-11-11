<?php

declare(strict_types=1);

require_once __DIR__ . '/../TrophyHistoryRecorder.php';

class GameCopyService
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
            trophy_group tg,
            tg_org
        SET
            tg.name = tg_org.name,
            tg.detail = tg_org.detail,
            tg.icon_url = tg_org.icon_url
        WHERE
            tg.np_communication_id = :parent_np_communication_id AND tg.group_id = tg_org.group_id
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

    private const TROPHY_UPDATE_QUERY = <<<'SQL'
        WITH
            tg_org AS(
            SELECT
                t.group_id,
                t.order_id,
                t.hidden,
                t.name,
                t.detail,
                t.icon_url,
                t.progress_target_value,
                t.reward_name,
                t.reward_image_url,
                tm.status
            FROM
                trophy t
                LEFT JOIN trophy_meta tm ON tm.trophy_id = t.id
            WHERE
                t.np_communication_id = :child_np_communication_id
        )
        UPDATE
            trophy tg
            JOIN tg_org ON tg.group_id = tg_org.group_id AND tg.order_id = tg_org.order_id
            JOIN trophy_meta tgm ON tgm.trophy_id = tg.id
        SET
            tg.hidden = tg_org.hidden,
            tg.name = tg_org.name,
            tg.detail = tg_org.detail,
            tg.icon_url = tg_org.icon_url,
            tg.progress_target_value = tg_org.progress_target_value,
            tg.reward_name = tg_org.reward_name,
            tg.reward_image_url = tg_org.reward_image_url,
            tgm.status = tg_org.status
        WHERE
            tg.np_communication_id = :parent_np_communication_id
        SQL;

    private const TROPHY_INSERT_QUERY = <<<'SQL'
        INSERT INTO
            trophy (
                np_communication_id,
                group_id,
                order_id,
                hidden,
                type,
                name,
                detail,
                icon_url,
                progress_target_value,
                reward_name,
                reward_image_url
            )
        SELECT
            :parent_np_communication_id,
            t.group_id,
            t.order_id,
            t.hidden,
            t.type,
            t.name,
            t.detail,
            t.icon_url,
            t.progress_target_value,
            t.reward_name,
            t.reward_image_url
        FROM
            trophy t
        WHERE
            t.np_communication_id = :child_np_communication_id
            AND NOT EXISTS (
                SELECT
                    1
                FROM
                    trophy existing
                WHERE
                    existing.np_communication_id = :parent_np_communication_id
                    AND existing.order_id = t.order_id
            )
            AND NOT EXISTS (
                SELECT
                    1
                FROM
                    trophy_merge tm
                WHERE
                    tm.child_np_communication_id = :child_np_communication_id
                    AND tm.child_group_id = t.group_id
                    AND tm.child_order_id = t.order_id
                    AND tm.parent_np_communication_id = :parent_np_communication_id
            )
        SQL;

    private const TROPHY_META_INSERT_QUERY = <<<'SQL'
        INSERT INTO
            trophy_meta (
                trophy_id,
                status,
                rarity_name
            )
        SELECT
            parent.id,
            tm.status,
            'NONE'
        FROM
            trophy parent
            INNER JOIN trophy t ON t.np_communication_id = :child_np_communication_id
                AND t.group_id = parent.group_id
                AND t.order_id = parent.order_id
            INNER JOIN trophy_meta tm ON tm.trophy_id = t.id
            LEFT JOIN trophy_meta existing ON existing.trophy_id = parent.id
        WHERE
            parent.np_communication_id = :parent_np_communication_id
            AND existing.trophy_id IS NULL
        SQL;

    private PDO $database;

    private ?PDOStatement $findTrophyIdStatement = null;

    private TrophyHistoryRecorder $historyRecorder;

    public function __construct(PDO $database, ?TrophyHistoryRecorder $historyRecorder = null)
    {
        $this->database = $database;
        $this->historyRecorder = $historyRecorder ?? new TrophyHistoryRecorder($database);
    }

    public function copyChildToParent(
        int $childId,
        int $parentId,
        bool $copyIconUrl = true,
        bool $copySetVersion = true
    ): void {
        $childNpCommunicationId = $this->getNpCommunicationId($childId);
        $parentNpCommunicationId = $this->getNpCommunicationId($parentId);

        $this->ensureChildIsNotMergeTitle($childNpCommunicationId);
        $this->ensureParentIsMergeTitle($parentNpCommunicationId);

        $this->copyTrophyTitle(
            $childNpCommunicationId,
            $parentNpCommunicationId,
            $copyIconUrl,
            $copySetVersion
        );

        if ($this->isBaseList($childNpCommunicationId)) {
            $this->copyNewTrophyGroups($childNpCommunicationId, $parentNpCommunicationId);
            $groupIdMapping = $this->copyConflictingTrophyGroups(
                $childNpCommunicationId,
                $parentNpCommunicationId,
                [],
                true
            );
            $this->copyTrophyGroups($childNpCommunicationId, $parentNpCommunicationId);
            $this->copyNewTrophies($childNpCommunicationId, $parentNpCommunicationId);
            $this->copyConflictingTrophies($childNpCommunicationId, $parentNpCommunicationId, $groupIdMapping);
            $this->copyTrophies($childNpCommunicationId, $parentNpCommunicationId);
        } else {
            $numericGroupIds = $this->getNumericGroupIds($childNpCommunicationId);
            $groupIdMapping = $this->copyConflictingTrophyGroups(
                $childNpCommunicationId,
                $parentNpCommunicationId,
                $numericGroupIds
            );
            $this->copyConflictingTrophies($childNpCommunicationId, $parentNpCommunicationId, $groupIdMapping);
        }

        $this->updateTrophyTitleCounts($parentNpCommunicationId);

        $this->recordCopyAction($childId, $parentId);

        $this->historyRecorder->recordByTitleId($parentId);
    }

    private function getNpCommunicationId(int $gameId): string
    {
        $query = $this->database->prepare('SELECT np_communication_id FROM trophy_title WHERE id = :id');
        $query->bindValue(':id', $gameId, PDO::PARAM_INT);
        $query->execute();

        $npCommunicationId = $query->fetchColumn();
        if ($npCommunicationId === false) {
            throw new RuntimeException('Unable to find the specified game.');
        }

        return (string) $npCommunicationId;
    }

    private function ensureChildIsNotMergeTitle(string $childNpCommunicationId): void
    {
        if (str_starts_with($childNpCommunicationId, 'MERGE')) {
            throw new RuntimeException("Child can't be a merge title.");
        }
    }

    private function ensureParentIsMergeTitle(string $parentNpCommunicationId): void
    {
        if (!str_starts_with($parentNpCommunicationId, 'MERGE')) {
            throw new RuntimeException('Parent must be a merge title.');
        }
    }

    private function copyTrophyGroups(string $childNpCommunicationId, string $parentNpCommunicationId): void
    {
        $query = $this->database->prepare(self::TROPHY_GROUP_UPDATE_QUERY);
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }

    private function copyNewTrophyGroups(string $childNpCommunicationId, string $parentNpCommunicationId): void
    {
        $query = $this->database->prepare(self::TROPHY_GROUP_INSERT_QUERY);
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }

    private function copyTrophyTitle(
        string $childNpCommunicationId,
        string $parentNpCommunicationId,
        bool $copyIconUrl,
        bool $copySetVersion
    ): void {
        $fields = ['parent.detail = child_title.detail'];

        if ($copyIconUrl) {
            $fields[] = 'parent.icon_url = child_title.icon_url';
        }

        if ($copySetVersion) {
            $fields[] = 'parent.set_version = child_title.set_version';
        }

        $query = $this->database->prepare(
            'WITH child_title AS (
                SELECT detail, icon_url, set_version
                FROM trophy_title
                WHERE np_communication_id = :child_np_communication_id
            )
            UPDATE trophy_title parent, child_title
            SET ' . implode(', ', $fields) . '
            WHERE parent.np_communication_id = :parent_np_communication_id'
        );
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }

    private function copyTrophies(string $childNpCommunicationId, string $parentNpCommunicationId): void
    {
        $query = $this->database->prepare(self::TROPHY_UPDATE_QUERY);
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }

    private function copyNewTrophies(string $childNpCommunicationId, string $parentNpCommunicationId): void
    {
        $query = $this->database->prepare(self::TROPHY_INSERT_QUERY);
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $metaQuery = $this->database->prepare(self::TROPHY_META_INSERT_QUERY);
        $metaQuery->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $metaQuery->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $metaQuery->execute();
        $metaQuery->closeCursor();
    }

    private function updateTrophyTitleCounts(string $npCommunicationId): void
    {
        $select = $this->database->prepare(
            'SELECT COALESCE(SUM(bronze), 0) AS bronze,
                    COALESCE(SUM(silver), 0) AS silver,
                    COALESCE(SUM(gold), 0) AS gold,
                    COALESCE(SUM(platinum), 0) AS platinum
             FROM   trophy_group
             WHERE  np_communication_id = :np_communication_id'
        );
        $select->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $select->execute();

        $counts = $select->fetch(PDO::FETCH_ASSOC) ?: [];
        $select->closeCursor();

        $bronze = (int) ($counts['bronze'] ?? 0);
        $silver = (int) ($counts['silver'] ?? 0);
        $gold = (int) ($counts['gold'] ?? 0);
        $platinum = (int) ($counts['platinum'] ?? 0);

        $update = $this->database->prepare(
            'UPDATE trophy_title
             SET    bronze = :bronze,
                    silver = :silver,
                    gold = :gold,
                    platinum = :platinum
             WHERE  np_communication_id = :np_communication_id'
        );
        $update->bindValue(':bronze', $bronze, PDO::PARAM_INT);
        $update->bindValue(':silver', $silver, PDO::PARAM_INT);
        $update->bindValue(':gold', $gold, PDO::PARAM_INT);
        $update->bindValue(':platinum', $platinum, PDO::PARAM_INT);
        $update->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $update->execute();
    }

    private function isBaseList(string $npCommunicationId): bool
    {
        $query = $this->database->prepare(
            'SELECT 1
             FROM   trophy_group
             WHERE  np_communication_id = :np_communication_id
             AND    group_id = :group_id
             LIMIT 1'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', 'default', PDO::PARAM_STR);
        $query->execute();

        $isBaseList = $query->fetchColumn() !== false;
        $query->closeCursor();

        return $isBaseList;
    }

    /**
     * @return string[]
     */
    private function getNumericGroupIds(string $npCommunicationId): array
    {
        $query = $this->database->prepare(
            'SELECT group_id
             FROM   trophy_group
             WHERE  np_communication_id = :np_communication_id
             ORDER BY group_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $groupIds = [];

        while (($groupId = $query->fetchColumn()) !== false) {
            $groupId = (string) $groupId;

            if ($this->parseNumericGroupId($groupId) === null) {
                continue;
            }

            $groupIds[] = $groupId;
        }

        $query->closeCursor();

        return $groupIds;
    }

    /**
     * @param string[] $forcedGroupIds
     * @return array<string, string>
     */
    private function copyConflictingTrophyGroups(
        string $childNpCommunicationId,
        string $parentNpCommunicationId,
        array $forcedGroupIds = [],
        bool $preserveGroupIds = false
    ): array {
        $conflictingGroupIds = $this->getConflictingGroupIds($childNpCommunicationId, $parentNpCommunicationId);

        if ($forcedGroupIds !== []) {
            foreach ($forcedGroupIds as $groupId) {
                if ($this->parseNumericGroupId($groupId) === null) {
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

        $groupOffset = $this->determineGroupOffset($existingGroupIds);
        $preferredOffset = $this->determinePreferredGroupOffset($existingGroupMappings);

        $select = $this->database->prepare(
            'SELECT group_id, name, detail, icon_url, bronze, silver, gold, platinum
             FROM   trophy_group
             WHERE  np_communication_id = :np_communication_id
             AND    group_id = :group_id'
        );
        $upsert = $this->database->prepare(
            'INSERT INTO trophy_group (np_communication_id, group_id, name, detail, icon_url, bronze, silver, gold, platinum)
             VALUES (:np_communication_id, :group_id, :name, :detail, :icon_url, :bronze, :silver, :gold, :platinum)
             ON DUPLICATE KEY UPDATE
                 name = VALUES(name),
                 detail = VALUES(detail),
                 icon_url = VALUES(icon_url),
                 bronze = VALUES(bronze),
                 silver = VALUES(silver),
                 gold = VALUES(gold),
                 platinum = VALUES(platinum)'
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

            $numericGroupId = $this->parseNumericGroupId($groupId);

            if ($numericGroupId === null) {
                $select->closeCursor();
                continue;
            }

            $targetGroupId = $groupIdMapping[$groupId] ?? null;

            if ($targetGroupId === null) {
                $targetGroupId = $this->findMatchingParentGroupId(
                    $groupId,
                    $childGroupTrophyNames,
                    $parentGroupTrophyNames,
                    $usedParentGroups
                );
            }

            if ($targetGroupId === null) {
                $candidateOffset = $preferredOffset ?? $groupOffset;
                $newGroupId = $this->formatGroupId($numericGroupId + $candidateOffset, (string) $group['group_id']);

                while (isset($existingGroupIds[$newGroupId])) {
                    if ($preferredOffset !== null) {
                        $preferredOffset = null;
                    }

                    $candidateOffset += 100;
                    $newGroupId = $this->formatGroupId($numericGroupId + $candidateOffset, (string) $group['group_id']);
                }

                $groupOffset = $candidateOffset;
                $targetGroupId = $newGroupId;
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
     * @param array<string, string> $groupIdMapping
     */
    private function copyConflictingTrophies(string $childNpCommunicationId, string $parentNpCommunicationId, array $groupIdMapping): void
    {
        if ($groupIdMapping === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($groupIdMapping), '?'));

        $query = $this->database->prepare(
            'SELECT t.group_id,
                    t.order_id,
                    t.hidden,
                    t.type,
                    t.name,
                    t.detail,
                    t.icon_url,
                    tm.status,
                    t.progress_target_value,
                    t.reward_name,
                    t.reward_image_url
             FROM   trophy t
                    JOIN trophy_meta tm ON tm.trophy_id = t.id
             WHERE  t.np_communication_id = ?
             AND    t.group_id IN (' . $placeholders . ')
             ORDER BY t.group_id, t.order_id'
        );

        $parameters = array_merge([$childNpCommunicationId], array_keys($groupIdMapping));
        $query->execute($parameters);

        $trophies = $query->fetchAll(PDO::FETCH_ASSOC);

        if ($trophies === []) {
            return;
        }

        $existingTrophyMappings = $this->getExistingTrophyMappings($childNpCommunicationId, $parentNpCommunicationId);
        $nextOrderId = $this->getNextOrderId($parentNpCommunicationId);

        $insert = $this->database->prepare(
            'INSERT INTO trophy (
                np_communication_id,
                group_id,
                order_id,
                hidden,
                type,
                name,
                detail,
                icon_url,
                progress_target_value,
                reward_name,
                reward_image_url
            ) VALUES (
                :np_communication_id,
                :group_id,
                :order_id,
                :hidden,
                :type,
                :name,
                :detail,
                :icon_url,
                :progress_target_value,
                :reward_name,
                :reward_image_url
            )'
        );
        $update = $this->database->prepare(
            'UPDATE trophy
             SET    hidden = :hidden,
                    type = :type,
                    name = :name,
                    detail = :detail,
                    icon_url = :icon_url,
                    progress_target_value = :progress_target_value,
                    reward_name = :reward_name,
                    reward_image_url = :reward_image_url
             WHERE  np_communication_id = :np_communication_id
             AND    group_id = :group_id
             AND    order_id = :order_id'
        );
        $metaUpsert = $this->database->prepare(
            'INSERT INTO trophy_meta (
                trophy_id,
                status
            ) VALUES (
                :trophy_id,
                :status
            )
            ON DUPLICATE KEY UPDATE
                status = VALUES(status)'
        );
        $exists = $this->database->prepare(
            'SELECT 1
             FROM   trophy
             WHERE  np_communication_id = :np_communication_id
             AND    group_id = :group_id
             AND    order_id = :order_id'
        );
        $findExisting = $this->database->prepare(
            'SELECT order_id
             FROM   trophy
             WHERE  np_communication_id = :np_communication_id
             AND    group_id = :group_id
             AND    name = :name
             LIMIT 1'
        );
        $merge = $this->database->prepare(
            'INSERT INTO trophy_merge (
                child_np_communication_id,
                child_group_id,
                child_order_id,
                parent_np_communication_id,
                parent_group_id,
                parent_order_id
            ) VALUES (
                :child_np_communication_id,
                :child_group_id,
                :child_order_id,
                :parent_np_communication_id,
                :parent_group_id,
                :parent_order_id
            )
            ON DUPLICATE KEY UPDATE
                parent_np_communication_id = VALUES(parent_np_communication_id),
                parent_group_id = VALUES(parent_group_id),
                parent_order_id = VALUES(parent_order_id)'
        );

        foreach ($trophies as $trophy) {
            $childGroupId = (string) $trophy['group_id'];
            $childOrderId = (int) $trophy['order_id'];
            $targetGroupId = $groupIdMapping[$childGroupId] ?? $childGroupId;
            $parentGroupId = $targetGroupId;
            $parentOrderId = null;

            $existingMapping = $existingTrophyMappings[$childGroupId][$childOrderId] ?? null;

            if ($existingMapping !== null) {
                $parentGroupId = $existingMapping['parent_group_id'];
                $parentOrderId = $existingMapping['parent_order_id'];

                if ($this->trophyExists($exists, $parentNpCommunicationId, $parentGroupId, $parentOrderId)) {
                    $this->updateParentTrophy($update, $metaUpsert, $parentNpCommunicationId, $parentGroupId, $parentOrderId, $trophy);
                    $this->upsertTrophyMergeMapping(
                        $merge,
                        $childNpCommunicationId,
                        $childGroupId,
                        $childOrderId,
                        $parentNpCommunicationId,
                        $parentGroupId,
                        $parentOrderId
                    );

                    continue;
                }

                $parentOrderId = null;
            }

            if ($parentOrderId === null) {
                $parentOrderId = $this->findExistingParentTrophyOrderId(
                    $findExisting,
                    $parentNpCommunicationId,
                    $parentGroupId,
                    (string) $trophy['name']
                );

                if ($parentOrderId !== null) {
                    $this->updateParentTrophy($update, $metaUpsert, $parentNpCommunicationId, $parentGroupId, $parentOrderId, $trophy);
                    $this->upsertTrophyMergeMapping(
                        $merge,
                        $childNpCommunicationId,
                        $childGroupId,
                        $childOrderId,
                        $parentNpCommunicationId,
                        $parentGroupId,
                        $parentOrderId
                    );

                    continue;
                }
            }

            $parentOrderId = ++$nextOrderId;

            $this->insertParentTrophy($insert, $metaUpsert, $parentNpCommunicationId, $parentGroupId, $parentOrderId, $trophy);
            $this->upsertTrophyMergeMapping(
                $merge,
                $childNpCommunicationId,
                $childGroupId,
                $childOrderId,
                $parentNpCommunicationId,
                $parentGroupId,
                $parentOrderId
            );
        }
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

            if ($this->parseNumericGroupId($groupId) === null) {
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
     * @return array<string, array<int, array{parent_group_id: string, parent_order_id: int}>>
     */
    private function getExistingTrophyMappings(string $childNpCommunicationId, string $parentNpCommunicationId): array
    {
        $query = $this->database->prepare(
            'SELECT child_group_id, child_order_id, parent_group_id, parent_order_id
             FROM   trophy_merge
             WHERE  child_np_communication_id = :child_np_communication_id
             AND    parent_np_communication_id = :parent_np_communication_id'
        );
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $mappings = [];

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $parentGroupId = (string) $row['parent_group_id'];

            if ($parentGroupId === '') {
                continue;
            }

            $childGroupId = (string) $row['child_group_id'];
            $childOrderId = (int) $row['child_order_id'];
            $mappings[$childGroupId][$childOrderId] = [
                'parent_group_id' => $parentGroupId,
                'parent_order_id' => (int) $row['parent_order_id'],
            ];
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

    /**
     * @param array<string, string[]> $childGroupTrophyNames
     * @param array<string, string[]> $parentGroupTrophyNames
     * @param array<string, bool> $usedParentGroups
     */
    private function findMatchingParentGroupId(
        string $childGroupId,
        array $childGroupTrophyNames,
        array $parentGroupTrophyNames,
        array $usedParentGroups
    ): ?string {
        $childNames = $childGroupTrophyNames[$childGroupId] ?? null;

        if ($childNames === null || $childNames === []) {
            return null;
        }

        foreach ($parentGroupTrophyNames as $parentGroupId => $parentNames) {
            $parentGroupIdString = (string) $parentGroupId;

            if ($parentGroupIdString === $childGroupId) {
                continue;
            }

            if (isset($usedParentGroups[$parentGroupIdString])) {
                continue;
            }

            if ($parentNames === $childNames) {
                return $parentGroupIdString;
            }
        }

        return null;
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

    private function trophyExists(PDOStatement $statement, string $parentNpCommunicationId, string $parentGroupId, int $parentOrderId): bool
    {
        $statement->execute([
            ':np_communication_id' => $parentNpCommunicationId,
            ':group_id' => $parentGroupId,
            ':order_id' => $parentOrderId,
        ]);

        $exists = $statement->fetchColumn() !== false;
        $statement->closeCursor();

        return $exists;
    }

    private function findExistingParentTrophyOrderId(PDOStatement $statement, string $parentNpCommunicationId, string $parentGroupId, string $trophyName): ?int
    {
        $statement->execute([
            ':np_communication_id' => $parentNpCommunicationId,
            ':group_id' => $parentGroupId,
            ':name' => $trophyName,
        ]);

        $orderId = $statement->fetchColumn();
        $statement->closeCursor();

        if ($orderId === false) {
            return null;
        }

        return (int) $orderId;
    }

    private function updateParentTrophy(
        PDOStatement $trophyStatement,
        PDOStatement $metaStatement,
        string $parentNpCommunicationId,
        string $parentGroupId,
        int $parentOrderId,
        array $trophy
    ): void {
        $trophyStatement->execute([
            ':hidden' => (int) $trophy['hidden'],
            ':type' => (string) $trophy['type'],
            ':name' => (string) $trophy['name'],
            ':detail' => (string) $trophy['detail'],
            ':icon_url' => (string) $trophy['icon_url'],
            ':progress_target_value' => $trophy['progress_target_value'] === null ? null : (int) $trophy['progress_target_value'],
            ':reward_name' => $trophy['reward_name'] === null ? null : (string) $trophy['reward_name'],
            ':reward_image_url' => $trophy['reward_image_url'] === null ? null : (string) $trophy['reward_image_url'],
            ':np_communication_id' => $parentNpCommunicationId,
            ':group_id' => $parentGroupId,
            ':order_id' => $parentOrderId,
        ]);

        $trophyStatement->closeCursor();

        $trophyId = $this->findTrophyId($parentNpCommunicationId, $parentGroupId, $parentOrderId);

        $this->upsertTrophyMeta($metaStatement, $trophyId, $trophy);
    }

    private function insertParentTrophy(
        PDOStatement $trophyStatement,
        PDOStatement $metaStatement,
        string $parentNpCommunicationId,
        string $parentGroupId,
        int $parentOrderId,
        array $trophy
    ): void {
        $trophyStatement->execute([
            ':np_communication_id' => $parentNpCommunicationId,
            ':group_id' => $parentGroupId,
            ':order_id' => $parentOrderId,
            ':hidden' => (int) $trophy['hidden'],
            ':type' => (string) $trophy['type'],
            ':name' => (string) $trophy['name'],
            ':detail' => (string) $trophy['detail'],
            ':icon_url' => (string) $trophy['icon_url'],
            ':progress_target_value' => $trophy['progress_target_value'] === null ? null : (int) $trophy['progress_target_value'],
            ':reward_name' => $trophy['reward_name'] === null ? null : (string) $trophy['reward_name'],
            ':reward_image_url' => $trophy['reward_image_url'] === null ? null : (string) $trophy['reward_image_url'],
        ]);

        $trophyId = (int) $this->database->lastInsertId();
        $trophyStatement->closeCursor();

        if ($trophyId <= 0) {
            $trophyId = $this->findTrophyId($parentNpCommunicationId, $parentGroupId, $parentOrderId);
        }

        $this->upsertTrophyMeta($metaStatement, $trophyId, $trophy);
    }

    private function upsertTrophyMeta(PDOStatement $statement, int $trophyId, array $trophy): void
    {
        $statement->execute([
            ':trophy_id' => $trophyId,
            ':status' => (int) $trophy['status'],
        ]);

        $statement->closeCursor();
    }

    private function findTrophyId(string $npCommunicationId, string $groupId, int $orderId): int
    {
        if ($this->findTrophyIdStatement === null) {
            $this->findTrophyIdStatement = $this->database->prepare(
                'SELECT id FROM trophy WHERE np_communication_id = :np_communication_id AND group_id = :group_id AND order_id = :order_id'
            );
        }

        $statement = $this->findTrophyIdStatement;
        $statement->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $statement->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $statement->execute();

        $id = $statement->fetchColumn();
        $statement->closeCursor();

        if ($id === false) {
            throw new RuntimeException('Unable to determine parent trophy ID.');
        }

        return (int) $id;
    }

    private function upsertTrophyMergeMapping(
        PDOStatement $statement,
        string $childNpCommunicationId,
        string $childGroupId,
        int $childOrderId,
        string $parentNpCommunicationId,
        string $parentGroupId,
        int $parentOrderId
    ): void {
        $statement->execute([
            ':child_np_communication_id' => $childNpCommunicationId,
            ':child_group_id' => $childGroupId,
            ':child_order_id' => $childOrderId,
            ':parent_np_communication_id' => $parentNpCommunicationId,
            ':parent_group_id' => $parentGroupId,
            ':parent_order_id' => $parentOrderId,
        ]);

        $statement->closeCursor();
    }

    /**
     * @param array<string, string> $existingGroupMappings
     */
    private function determinePreferredGroupOffset(array $existingGroupMappings): ?int
    {
        foreach ($existingGroupMappings as $parentGroupId) {
            $numericGroupId = $this->parseNumericGroupId($parentGroupId);

            if ($numericGroupId === null) {
                continue;
            }

            $block = intdiv($numericGroupId, 100);

            return $block * 100;
        }

        return null;
    }

    /**
     * @param array<string, bool> $existingGroupIds
     */
    private function determineGroupOffset(array $existingGroupIds): int
    {
        $maxBlock = -1;

        foreach ($existingGroupIds as $groupId => $_) {
            $groupId = (string) $groupId;

            $numericGroupId = $this->parseNumericGroupId($groupId);

            if ($numericGroupId === null) {
                continue;
            }

            $block = intdiv($numericGroupId, 100);

            if ($block > $maxBlock) {
                $maxBlock = $block;
            }
        }

        if ($maxBlock < 0) {
            $maxBlock = 0;
        }

        return ($maxBlock + 1) * 100;
    }

    private function parseNumericGroupId(string $groupId): ?int
    {
        if (!ctype_digit($groupId)) {
            return null;
        }

        $trimmed = ltrim($groupId, '0');

        if ($trimmed === '') {
            return 0;
        }

        return (int) $trimmed;
    }

    private function formatGroupId(int $numericValue, string $originalGroupId): string
    {
        $length = max(strlen($originalGroupId), 3);

        return str_pad((string) $numericValue, $length, '0', STR_PAD_LEFT);
    }

    private function getNextOrderId(string $npCommunicationId): int
    {
        $query = $this->database->prepare('SELECT MAX(order_id) FROM trophy WHERE np_communication_id = :np_communication_id');
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $maxOrderId = $query->fetchColumn();

        if ($maxOrderId === false || $maxOrderId === null) {
            return -1;
        }

        return (int) $maxOrderId;
    }

    private function recordCopyAction(int $childId, int $parentId): void
    {
        $query = $this->database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`, `param_2`) VALUES ('GAME_COPY', :param_1, :param_2)");
        $query->bindValue(':param_1', $childId, PDO::PARAM_INT);
        $query->bindValue(':param_2', $parentId, PDO::PARAM_INT);
        $query->execute();
    }
}
