<?php

declare(strict_types=1);

/**
 * Copies conflicting trophies from a child title into a MERGE parent during admin copy.
 */
final class MergeTrophyCopier
{
    private ?PDOStatement $findTrophyIdStatement = null;

    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @param array<string, string> $groupIdMapping
     */
    public function copyConflictingTrophies(
        string $childNpCommunicationId,
        string $parentNpCommunicationId,
        array $groupIdMapping
    ): void {
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
            AS new
            ON DUPLICATE KEY UPDATE
                status = new.status'
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
            AS new
            ON DUPLICATE KEY UPDATE
                parent_np_communication_id = new.parent_np_communication_id,
                parent_group_id = new.parent_group_id,
                parent_order_id = new.parent_order_id'
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
            ':progress_target_value' => isset($trophy['progress_target_value']) ? (int) $trophy['progress_target_value'] : null,
            ':reward_name' => isset($trophy['reward_name']) ? (string) $trophy['reward_name'] : null,
            ':reward_image_url' => isset($trophy['reward_image_url']) ? (string) $trophy['reward_image_url'] : null,
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
            ':progress_target_value' => isset($trophy['progress_target_value']) ? (int) $trophy['progress_target_value'] : null,
            ':reward_name' => isset($trophy['reward_name']) ? (string) $trophy['reward_name'] : null,
            ':reward_image_url' => isset($trophy['reward_image_url']) ? (string) $trophy['reward_image_url'] : null,
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
}
