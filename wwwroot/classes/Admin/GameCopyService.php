<?php

declare(strict_types=1);

require_once __DIR__ . '/../TrophyHistoryRecorder.php';
require_once __DIR__ . '/../ChangelogEntry.php';
require_once __DIR__ . '/MergeTrophyCopier.php';
require_once __DIR__ . '/MergeTrophyGroupCopier.php';
require_once __DIR__ . '/TrophyGroupConflictResolver.php';

class GameCopyService
{
    private const string TROPHY_UPDATE_QUERY = <<<'SQL'
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

    private const string TROPHY_INSERT_QUERY = <<<'SQL'
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

    private const string TROPHY_META_INSERT_QUERY = <<<'SQL'
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

    private readonly PDO $database;

    private readonly TrophyHistoryRecorder $historyRecorder;

    private readonly TrophyGroupConflictResolver $groupConflictResolver;

    private readonly MergeTrophyGroupCopier $groupCopier;

    private readonly MergeTrophyCopier $trophyCopier;

    public function __construct(
        PDO $database,
        ?TrophyHistoryRecorder $historyRecorder = null,
        ?TrophyGroupConflictResolver $groupConflictResolver = null,
        ?MergeTrophyGroupCopier $groupCopier = null,
        ?MergeTrophyCopier $trophyCopier = null,
    ) {
        $this->database = $database;
        $this->historyRecorder = $historyRecorder ?? new TrophyHistoryRecorder($database);
        $this->groupConflictResolver = $groupConflictResolver ?? new TrophyGroupConflictResolver();
        $this->groupCopier = $groupCopier ?? new MergeTrophyGroupCopier($database, $this->groupConflictResolver);
        $this->trophyCopier = $trophyCopier ?? new MergeTrophyCopier($database);
    }

    public function copyChildToParent(
        int $childId,
        int $parentId,
        bool $copyIconUrl = true,
        bool $copySetVersion = true
    ): void {
        $this->database->beginTransaction();

        try {
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
                $this->groupCopier->copyNewTrophyGroups($childNpCommunicationId, $parentNpCommunicationId);
                $groupIdMapping = $this->groupCopier->copyConflictingTrophyGroups(
                    $childNpCommunicationId,
                    $parentNpCommunicationId,
                    [],
                    true
                );
                $this->groupCopier->copyTrophyGroups($childNpCommunicationId, $parentNpCommunicationId);
                $this->copyNewTrophies($childNpCommunicationId, $parentNpCommunicationId);
                $this->trophyCopier->copyConflictingTrophies($childNpCommunicationId, $parentNpCommunicationId, $groupIdMapping);
                $this->copyTrophies($childNpCommunicationId, $parentNpCommunicationId);
            } else {
                $numericGroupIds = $this->getNumericGroupIds($childNpCommunicationId);
                $groupIdMapping = $this->groupCopier->copyConflictingTrophyGroups(
                    $childNpCommunicationId,
                    $parentNpCommunicationId,
                    $numericGroupIds
                );
                $this->trophyCopier->copyConflictingTrophies($childNpCommunicationId, $parentNpCommunicationId, $groupIdMapping);
            }

            $this->updateTrophyTitleCounts($parentNpCommunicationId);

            $this->recordCopyAction($childId, $parentId);

            $this->historyRecorder->recordByTitleId($parentId);

            $this->database->commit();
        } catch (Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }
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
            UPDATE trophy_title parent
            INNER JOIN child_title ON 1 = 1
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

            if ($this->groupConflictResolver->parseNumericGroupId($groupId) === null) {
                continue;
            }

            $groupIds[] = $groupId;
        }

        $query->closeCursor();

        return $groupIds;
    }

    private function recordCopyAction(int $childId, int $parentId): void
    {
        $query = $this->database->prepare(
            'INSERT INTO `psn100_change` (`change_type`, `param_1`, `param_2`) VALUES (:change_type, :param_1, :param_2)'
        );
        $query->bindValue(':change_type', ChangelogEntryType::GAME_COPY->value, PDO::PARAM_STR);
        $query->bindValue(':param_1', $childId, PDO::PARAM_INT);
        $query->bindValue(':param_2', $parentId, PDO::PARAM_INT);
        $query->execute();
    }
}
