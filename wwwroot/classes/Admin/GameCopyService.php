<?php

declare(strict_types=1);

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

    private const TROPHY_TITLE_UPDATE_QUERY = <<<'SQL'
        WITH
            child_title AS (
            SELECT
                icon_url
            FROM
                trophy_title
            WHERE
                np_communication_id = :child_np_communication_id
        )
        UPDATE
            trophy_title parent,
            child_title
        SET
            parent.icon_url = child_title.icon_url
        WHERE
            parent.np_communication_id = :parent_np_communication_id
        SQL;

    private const TROPHY_UPDATE_QUERY = <<<'SQL'
        WITH
            tg_org AS(
            SELECT
                group_id,
                order_id,
                hidden,
                name,
                detail,
                icon_url,
                progress_target_value,
                reward_name,
                reward_image_url
            FROM
                trophy
            WHERE
                np_communication_id = :child_np_communication_id
        )
        UPDATE
            trophy tg,
            tg_org
        SET
            tg.hidden = tg_org.hidden,
            tg.name = tg_org.name,
            tg.detail = tg_org.detail,
            tg.icon_url = tg_org.icon_url,
            tg.progress_target_value = tg_org.progress_target_value,
            tg.reward_name = tg_org.reward_name,
            tg.reward_image_url = tg_org.reward_image_url
        WHERE
            tg.np_communication_id = :parent_np_communication_id AND tg.group_id = tg_org.group_id AND tg.order_id = tg_org.order_id
        SQL;

    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function copyChildToParent(int $childId, int $parentId): void
    {
        $childNpCommunicationId = $this->getNpCommunicationId($childId);
        $parentNpCommunicationId = $this->getNpCommunicationId($parentId);

        $this->ensureChildIsNotMergeTitle($childNpCommunicationId);
        $this->ensureParentIsMergeTitle($parentNpCommunicationId);

        $this->copyTrophyTitle($childNpCommunicationId, $parentNpCommunicationId);
        $this->copyTrophyGroups($childNpCommunicationId, $parentNpCommunicationId);
        $this->copyTrophies($childNpCommunicationId, $parentNpCommunicationId);
        $this->recordCopyAction($childId, $parentId);
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

    private function copyTrophyTitle(string $childNpCommunicationId, string $parentNpCommunicationId): void
    {
        $query = $this->database->prepare(self::TROPHY_TITLE_UPDATE_QUERY);
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

    private function recordCopyAction(int $childId, int $parentId): void
    {
        $query = $this->database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`, `param_2`) VALUES ('GAME_COPY', :param_1, :param_2)");
        $query->bindValue(':param_1', $childId, PDO::PARAM_INT);
        $query->bindValue(':param_2', $parentId, PDO::PARAM_INT);
        $query->execute();
    }
}
