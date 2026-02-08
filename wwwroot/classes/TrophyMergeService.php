<?php

declare(strict_types=1);

require_once __DIR__ . '/Admin/GameCopyService.php';
require_once __DIR__ . '/Admin/TrophyMergeProgressListener.php';

class TrophyMergeService
{
    private const PLATFORM_ORDER = ['PS3', 'PSVITA', 'PS4', 'PSVR', 'PS5', 'PSVR2', 'PC'];

    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function mergeSpecificTrophies(int $parentTrophyId, array $childTrophyIds): string
    {
        if (empty($childTrophyIds)) {
            throw new InvalidArgumentException('At least one child trophy is required.');
        }

        $parentTrophy = $this->getTrophyById($parentTrophyId);

        if (!str_starts_with($parentTrophy['np_communication_id'], 'MERGE')) {
            throw new InvalidArgumentException('Parent must be a merge title.');
        }

        foreach ($childTrophyIds as $childTrophyId) {
            $childTrophyId = (int) $childTrophyId;
            $childTrophy = $this->getTrophyById($childTrophyId);

            if (str_starts_with($childTrophy['np_communication_id'], 'MERGE')) {
                throw new InvalidArgumentException("Child can't be a merge title.");
            }

            $this->insertTrophyMergeMappingFromIds($childTrophyId, $parentTrophyId);
            $this->markGameAsMergedByNpId($childTrophy['np_communication_id']);

            $childGameId = $this->getGameIdByTrophyId($childTrophyId);

            $this->copyTrophyEarned(
                $childTrophy['np_communication_id'],
                $childTrophy['group_id'],
                (int) $childTrophy['order_id'],
                $parentTrophy['np_communication_id'],
                $parentTrophy['group_id'],
                (int) $parentTrophy['order_id']
            );

            $this->updateTrophyGroupPlayer($childGameId);
            $this->updateTrophyTitlePlayer($childGameId);
        }

        return 'The trophies have been merged.';
    }

    public function mergeGames(
        int $childGameId,
        int $parentGameId,
        string $method,
        ?TrophyMergeProgressListener $progressListener = null
    ): string
    {
        $childNpCommunicationId = $this->getGameNpCommunicationId($childGameId);

        if (str_starts_with($childNpCommunicationId, 'MERGE')) {
            throw new InvalidArgumentException("Child can't be a merge title.");
        }

        $parentNpCommunicationId = $this->getGameNpCommunicationId($parentGameId);

        if (!str_starts_with($parentNpCommunicationId, 'MERGE')) {
            throw new InvalidArgumentException('Parent must be a merge title.');
        }

        $this->notifyProgress($progressListener, 10, 'Validating merge configuration…');

        $message = '';

        $this->beginTransaction();

        try {
            switch ($method) {
                case 'name':
                    $this->notifyProgress($progressListener, 30, 'Matching trophies by name…');
                    $message .= $this->insertMappingsByName($childGameId, $parentGameId);
                    break;
                case 'icon':
                    $this->notifyProgress($progressListener, 30, 'Matching trophies by icon…');
                    $message .= $this->insertMappingsByIcon($childGameId, $parentGameId);
                    break;
                case 'order':
                    $this->notifyProgress($progressListener, 30, 'Matching trophies by list order…');
                    $this->insertMappingsByOrder($childGameId, $parentGameId);
                    break;
                default:
                    throw new InvalidArgumentException('Wrong input');
            }

            $this->commitTransaction();
        } catch (Throwable $exception) {
            $this->rollBackTransaction();
            throw $exception;
        }

        $this->notifyProgress($progressListener, 55, 'Trophy mappings saved.');
        $this->notifyProgress($progressListener, 60, 'Preparing to mark child game as merged…');
        $this->notifyProgress($progressListener, 62, 'Marking child game as merged…');
        $this->markGameAsMergedById($childGameId);
        $this->notifyProgress($progressListener, 65, 'Child game marked as merged.');
        $this->notifyProgress($progressListener, 70, 'Preparing to copy merged trophies…');
        $this->notifyProgress($progressListener, 72, 'Copying merged trophies…');
        $this->copyMergedTrophies($childNpCommunicationId, $progressListener);
        $this->notifyProgress($progressListener, 75, 'Merged trophies copied.');
        $this->notifyProgress($progressListener, 80, 'Updating player trophy groups…');
        $this->updateTrophyGroupPlayer($childGameId);
        $this->notifyProgress($progressListener, 85, 'Player trophy groups updated.');
        $this->notifyProgress($progressListener, 88, 'Updating player trophy titles…');
        $this->updateTrophyTitlePlayer($childGameId);
        $this->notifyProgress($progressListener, 92, 'Player trophy titles updated.');
        $this->notifyProgress($progressListener, 94, 'Updating parent relationship…');
        $this->updateParentRelationship($childNpCommunicationId, $parentNpCommunicationId);
        $this->notifyProgress($progressListener, 96, 'Parent relationship updated.');
        $this->notifyProgress($progressListener, 98, 'Logging merge activity…');
        $this->logChange('GAME_MERGE', $childGameId, $parentGameId);
        $this->notifyProgress($progressListener, 100, 'Merge process complete.');

        return $message . 'The games have been merged.';
    }

    public function cloneGame(int $childGameId): string
    {
        $this->cloneGameInternal($childGameId);

        return 'The game have been cloned.';
    }

    /**
     * @return array{clone_game_id:int, clone_np_communication_id:string}
     */
    public function cloneGameWithInfo(int $childGameId): array
    {
        return $this->cloneGameInternal($childGameId);
    }

    public function copyGameData(string $sourceNpCommunicationId, string $targetNpCommunicationId): void
    {
        if ($sourceNpCommunicationId === $targetNpCommunicationId) {
            return;
        }

        $sourceGameId = $this->getGameIdByNpCommunicationId($sourceNpCommunicationId);
        $targetGameId = $this->getGameIdByNpCommunicationId($targetNpCommunicationId);

        $gameCopyService = new GameCopyService($this->database);
        $gameCopyService->copyChildToParent($sourceGameId, $targetGameId);
    }

    public function recomputeMergeProgressByParent(string $parentNpCommunicationId): void
    {
        if (!str_starts_with($parentNpCommunicationId, 'MERGE')) {
            throw new InvalidArgumentException('Parent must be a merge title.');
        }

        $childNpCommunicationIds = $this->getMergeChildrenByParent($parentNpCommunicationId);

        if ($childNpCommunicationIds === []) {
            throw new RuntimeException('Unable to locate child trophy titles.');
        }

        $this->updateTrophyGroupPlayerForMerge($parentNpCommunicationId, $childNpCommunicationIds);
        $this->updateTrophyTitlePlayerForMerge($parentNpCommunicationId, $childNpCommunicationIds);
    }

    /**
     * @return array{clone_game_id:int, clone_np_communication_id:string}
     */
    private function cloneGameInternal(int $childGameId): array
    {
        $childNpCommunicationId = $this->getGameNpCommunicationId($childGameId);

        if (str_starts_with($childNpCommunicationId, 'MERGE')) {
            throw new InvalidArgumentException("Can't clone an already cloned game.");
        }

        $analyze = $this->database->prepare('ANALYZE TABLE `trophy_title`');
        $analyze->execute();

        $query = $this->database->prepare(
            <<<'SQL'
            SELECT auto_increment
            FROM   information_schema.tables
            WHERE  table_name = 'trophy_title'
SQL
        );
        $query->execute();

        $gameId = $query->fetchColumn();

        if ($gameId === false) {
            throw new RuntimeException('Unable to determine next trophy title identifier.');
        }

        $gameId = (int) $gameId;

        $cloneNpCommunicationId = 'MERGE_' . str_pad((string) $gameId, 6, '0', STR_PAD_LEFT);

        $cloneGameId = null;

        $this->executeTransaction(function () use (
            $cloneNpCommunicationId,
            $childGameId,
            $childNpCommunicationId,
            &$cloneGameId
        ): void {
            $query = $this->database->prepare(
                <<<'SQL'
                INSERT INTO trophy_title
                            (np_communication_id,
                             name,
                             detail,
                             icon_url,
                             platform,
                             bronze,
                             silver,
                             gold,
                             platinum,
                             set_version)
                SELECT :np_communication_id,
                       name,
                       detail,
                       icon_url,
                       platform,
                       bronze,
                       silver,
                       gold,
                       platinum,
                       set_version
                FROM   trophy_title
                WHERE  id = :id
SQL
            );
            $query->bindValue(':np_communication_id', $cloneNpCommunicationId, PDO::PARAM_STR);
            $query->bindValue(':id', $childGameId, PDO::PARAM_INT);
            $query->execute();

            $insertedGameId = $this->database->lastInsertId();

            if ($insertedGameId === false) {
                throw new RuntimeException('Unable to determine cloned trophy title identifier.');
            }

            $cloneGameId = (int) $insertedGameId;

            $metaInsert = $this->database->prepare(
                <<<'SQL'
                INSERT INTO trophy_title_meta (
                    np_communication_id,
                    owners,
                    difficulty,
                    message,
                    status,
                    recent_players,
                    owners_completed,
                    parent_np_communication_id,
                    region,
                    rarity_points
                )
                SELECT
                    :np_communication_id,
                    owners,
                    difficulty,
                    message,
                    CASE WHEN status = 2 THEN 0 ELSE status END,
                    recent_players,
                    owners_completed,
                    NULL,
                    '',
                    rarity_points
                FROM trophy_title_meta
                WHERE np_communication_id = :child_np_communication_id
SQL
            );
            $metaInsert->bindValue(':np_communication_id', $cloneNpCommunicationId, PDO::PARAM_STR);
            $metaInsert->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
            $metaInsert->execute();

            if ($metaInsert->rowCount() === 0) {
                $defaultMetaInsert = $this->database->prepare(
                    <<<'SQL'
                    INSERT INTO trophy_title_meta (
                        np_communication_id,
                        message
                    )
                    VALUES (
                        :np_communication_id,
                        ''
                    )
SQL
                );
                $defaultMetaInsert->bindValue(':np_communication_id', $cloneNpCommunicationId, PDO::PARAM_STR);
                $defaultMetaInsert->execute();
            }

            $query = $this->database->prepare(
                <<<'SQL'
                INSERT INTO trophy_group
                            (np_communication_id,
                             group_id,
                             name,
                             detail,
                             icon_url,
                             bronze,
                             silver,
                             gold,
                             platinum)
                SELECT :np_communication_id,
                       group_id,
                       name,
                       detail,
                       icon_url,
                       bronze,
                       silver,
                       gold,
                       platinum
                FROM   trophy_group
                WHERE  np_communication_id = :child_np_communication_id
SQL
            );
            $query->bindValue(':np_communication_id', $cloneNpCommunicationId, PDO::PARAM_STR);
            $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
            $query->execute();

            $query = $this->database->prepare(
                <<<'SQL'
                INSERT INTO trophy
                            (np_communication_id,
                             group_id,
                             order_id,
                             hidden,
                             type,
                             name,
                             detail,
                             icon_url,
                             progress_target_value,
                             reward_name,
                             reward_image_url)
                SELECT :np_communication_id,
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
                FROM   trophy
                WHERE  np_communication_id = :child_np_communication_id
SQL
            );
            $query->bindValue(':np_communication_id', $cloneNpCommunicationId, PDO::PARAM_STR);
            $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
            $query->execute();

            $trophyMetaInsert = $this->database->prepare(
                <<<'SQL'
                INSERT INTO trophy_meta (
                    trophy_id,
                    rarity_percent,
                    rarity_point,
                    status,
                    owners,
                    rarity_name
                )
                SELECT
                    parent.id,
                    tm.rarity_percent,
                    tm.rarity_point,
                    tm.status,
                    tm.owners,
                    tm.rarity_name
                FROM trophy parent
                INNER JOIN trophy child ON child.np_communication_id = :child_np_communication_id
                    AND child.group_id = parent.group_id
                    AND child.order_id = parent.order_id
                INNER JOIN trophy_meta tm ON tm.trophy_id = child.id
                LEFT JOIN trophy_meta existing ON existing.trophy_id = parent.id
                WHERE parent.np_communication_id = :parent_np_communication_id
                    AND existing.trophy_id IS NULL
SQL
            );
            $trophyMetaInsert->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
            $trophyMetaInsert->bindValue(':parent_np_communication_id', $cloneNpCommunicationId, PDO::PARAM_STR);
            $trophyMetaInsert->execute();

            $this->cloneGameHistory($childGameId, $cloneGameId);
        });

        if ($cloneGameId === null) {
            throw new RuntimeException('Unable to determine cloned trophy title identifier.');
        }

        $this->logChange('GAME_CLONE', $childGameId, $cloneGameId);

        return [
            'clone_game_id' => $cloneGameId,
            'clone_np_communication_id' => $cloneNpCommunicationId,
        ];
    }

    private function getTrophyById(int $trophyId): array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT np_communication_id, group_id, order_id
            FROM   trophy
            WHERE  id = :trophy_id
SQL
        );
        $query->bindValue(':trophy_id', $trophyId, PDO::PARAM_INT);
        $query->execute();

        $trophy = $query->fetch(PDO::FETCH_ASSOC);

        if ($trophy === false) {
            throw new InvalidArgumentException('Trophy not found.');
        }

        $trophy['order_id'] = (int) $trophy['order_id'];

        return $trophy;
    }

    private function getGameIdByTrophyId(int $trophyId): int
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT id
            FROM   trophy_title
            WHERE  np_communication_id = (SELECT np_communication_id
                                            FROM   trophy
                                            WHERE  id = :child_trophy_id)
SQL
        );
        $query->bindValue(':child_trophy_id', $trophyId, PDO::PARAM_INT);
        $query->execute();

        $childGameId = $query->fetchColumn();

        if ($childGameId === false) {
            throw new RuntimeException('Unable to locate child game identifier.');
        }

        return (int) $childGameId;
    }

    private function copyTrophyEarned(
        string $childNpCommunicationId,
        string $childGroupId,
        int $childOrderId,
        string $parentNpCommunicationId,
        string $parentGroupId,
        int $parentOrderId
    ): void {
        $query = $this->database->prepare(
            <<<'SQL'
            INSERT INTO trophy_earned(
                np_communication_id,
                group_id,
                order_id,
                account_id,
                earned_date,
                progress,
                earned
            )
            SELECT
                new.np_communication_id,
                new.group_id,
                new.order_id,
                new.account_id,
                new.earned_date,
                new.progress,
                new.earned
            FROM (
                SELECT
                    :parent_np_communication_id AS np_communication_id,
                    :parent_group_id AS group_id,
                    :parent_order_id AS order_id,
                    child.account_id AS account_id,
                    CASE
                        WHEN existing.earned_date IS NULL THEN child.earned_date
                        WHEN child.earned_date IS NULL THEN existing.earned_date
                        WHEN child.earned_date < existing.earned_date THEN child.earned_date
                        ELSE existing.earned_date
                    END AS earned_date,
                    CASE
                        WHEN existing.progress IS NULL THEN child.progress
                        WHEN child.progress IS NULL THEN existing.progress
                        WHEN child.progress > existing.progress THEN child.progress
                        ELSE existing.progress
                    END AS progress,
                    CASE
                        WHEN child.earned = 1 THEN 1
                        WHEN existing.earned = 1 THEN 1
                        ELSE COALESCE(existing.earned, 0)
                    END AS earned
                FROM
                    trophy_earned AS child
                LEFT JOIN trophy_earned AS existing ON existing.np_communication_id = :parent_np_communication_id
                    AND existing.group_id = :parent_group_id
                    AND existing.order_id = :parent_order_id
                    AND existing.account_id = child.account_id
                WHERE
                    child.np_communication_id = :child_np_communication_id
                    AND child.order_id = :child_order_id
            ) AS new
            ON DUPLICATE KEY
            UPDATE
                earned_date = new.earned_date,
                progress = new.progress,
                earned = new.earned
SQL
        );
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':child_order_id', $childOrderId, PDO::PARAM_INT);
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':parent_group_id', $parentGroupId, PDO::PARAM_STR);
        $query->bindValue(':parent_order_id', $parentOrderId, PDO::PARAM_INT);
        $query->execute();
    }

    private function updateTrophyGroupPlayer(int $childGameId): void
    {
        $childNpCommunicationId = $this->getGameNpCommunicationId($childGameId);

        $mergeData = $this->getMergeParentAndChildren($childNpCommunicationId);
        $parentNpCommunicationId = $mergeData['parent_np_communication_id'];
        $childNpCommunicationIds = array_values($mergeData['child_np_communication_ids']);

        $this->updateTrophyGroupPlayerForMerge($parentNpCommunicationId, $childNpCommunicationIds);
    }

    /**
     * @param list<string> $childNpCommunicationIds
     */
    private function updateTrophyGroupPlayerForMerge(string $parentNpCommunicationId, array $childNpCommunicationIds): void
    {
        if ($childNpCommunicationIds === []) {
            throw new RuntimeException('Unable to locate child trophy titles.');
        }

        $childPlaceholders = [];
        foreach (array_keys($childNpCommunicationIds) as $index) {
            $childPlaceholders[] = ':child_np_' . $index;
        }
        $childListSql = implode(', ', $childPlaceholders);

        $groups = $this->database->prepare(
            <<<'SQL'
            SELECT DISTINCT
                parent_group_id
            FROM
                trophy_merge
            WHERE
                parent_np_communication_id = :parent_np_communication_id
SQL
        );
        $groups->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $groups->execute();

        while ($group = $groups->fetch(PDO::FETCH_ASSOC)) {
            $query = $this->database->prepare(
                <<<'SQL'
                INSERT INTO trophy_group_player(
                    np_communication_id,
                    group_id,
                    account_id,
                    bronze,
                    silver,
                    gold,
                    platinum,
                    progress
                ) WITH tg AS(
                    SELECT
                        platinum,
                        bronze * 15 + silver * 30 + gold * 90 AS max_score
                    FROM
                        trophy_group
                    WHERE
                        np_communication_id = :np_communication_id AND group_id = :group_id
                ),
                player AS(
                    SELECT
                        account_id,
                        SUM(TYPE = 'bronze') AS bronze,
                        SUM(TYPE = 'silver') AS silver,
                        SUM(TYPE = 'gold') AS gold,
                        SUM(TYPE = 'platinum') AS platinum,
                        SUM(TYPE = 'bronze') * 15 + SUM(TYPE = 'silver') * 30 + SUM(TYPE = 'gold') * 90 AS score
                    FROM
                        trophy_earned te
                    JOIN trophy t ON
                        t.np_communication_id = te.np_communication_id AND t.order_id = te.order_id
                    JOIN trophy_meta tm ON tm.trophy_id = t.id AND tm.status = 0
                    WHERE
                        te.np_communication_id = :np_communication_id AND te.group_id = :group_id AND te.earned = 1
                    GROUP BY
                        account_id
                )
                SELECT
                    *
                FROM
                    (
                    SELECT
                        :np_communication_id,
                        :group_id,
                        player.account_id,
                        player.bronze,
                        player.silver,
                        player.gold,
                        player.platinum,
                        IF(
                            player.score = 0,
                            0,
                            IFNULL(
                                GREATEST(
                                    FLOOR(
                                        IF(
                                            (player.score / NULLIF(tg.max_score, 0)) * 100 = 100 AND tg.platinum = 1 AND player.platinum = 0,
                                            99,
                                            (player.score / NULLIF(tg.max_score, 0)) * 100
                                        )
                                    ),
                                    1
                                ),
                                0
                            )
                        ) AS progress
                    FROM
                        tg,
                        player
                ) AS NEW
                ON DUPLICATE KEY
                UPDATE
                    bronze = NEW.bronze,
                    silver = NEW.silver,
                    gold = NEW.gold,
                    platinum = NEW.platinum,
                    progress = NEW.progress
SQL
            );
            $query->bindValue(':np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
            $query->bindValue(':group_id', $group['parent_group_id'], PDO::PARAM_STR);
            $query->execute();

            $zeroProgressSql = sprintf(
                <<<'SQL'
                INSERT IGNORE
                INTO trophy_group_player(
                    np_communication_id,
                    group_id,
                    account_id,
                    bronze,
                    silver,
                    gold,
                    platinum,
                    progress
                ) WITH player AS(
                    SELECT
                        account_id
                    FROM
                        trophy_group_player tgp
                    WHERE
                        tgp.bronze = 0
                        AND tgp.silver = 0
                        AND tgp.gold = 0
                        AND tgp.platinum = 0
                        AND tgp.progress = 0
                        AND tgp.np_communication_id IN (%s)
                        AND tgp.group_id = :group_id
                )
                SELECT
                    :np_communication_id,
                    :group_id,
                    player.account_id,
                    0,
                    0,
                    0,
                    0,
                    0
                FROM
                    player
SQL
            ,
                $childListSql
            );
            $query = $this->database->prepare($zeroProgressSql);
            $query->bindValue(':np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
            $query->bindValue(':group_id', $group['parent_group_id'], PDO::PARAM_STR);
            foreach ($childNpCommunicationIds as $index => $childNpCommunicationId) {
                $query->bindValue(':child_np_' . $index, $childNpCommunicationId, PDO::PARAM_STR);
            }
            $query->execute();
        }
    }

    private function updateTrophyTitlePlayer(int $childGameId): void
    {
        $childNpCommunicationId = $this->getGameNpCommunicationId($childGameId);

        $mergeData = $this->getMergeParentAndChildren($childNpCommunicationId);
        $parentNpCommunicationId = $mergeData['parent_np_communication_id'];
        $childNpCommunicationIds = array_values($mergeData['child_np_communication_ids']);

        $this->updateTrophyTitlePlayerForMerge($parentNpCommunicationId, $childNpCommunicationIds);
    }

    /**
     * @param list<string> $childNpCommunicationIds
     */
    private function updateTrophyTitlePlayerForMerge(string $parentNpCommunicationId, array $childNpCommunicationIds): void
    {
        if ($childNpCommunicationIds === []) {
            throw new RuntimeException('Unable to locate child trophy titles.');
        }

        $childPlaceholders = [];
        foreach (array_keys($childNpCommunicationIds) as $index) {
            $childPlaceholders[] = ':child_np_' . $index;
        }
        $childListSql = implode(', ', $childPlaceholders);

        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                platinum,
                bronze * 15 + silver * 30 + gold * 90 AS max_score
            FROM
                trophy_title
            WHERE
                np_communication_id = :np_communication_id
SQL
        );
        $query->bindValue(':np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $trophyTitle = $query->fetch(PDO::FETCH_ASSOC);

        if ($trophyTitle === false) {
            throw new RuntimeException('Unable to load trophy title data.');
        }

        $playerInsertSql = sprintf(
            <<<'SQL'
            INSERT INTO trophy_title_player(
                np_communication_id,
                account_id,
                bronze,
                silver,
                gold,
                platinum,
                progress,
                last_updated_date
            ) WITH child_players AS(
                SELECT
                    ttp.account_id,
                    MAX(ttp.last_updated_date) AS last_updated_date
                FROM
                    trophy_title_player ttp
                WHERE
                    ttp.np_communication_id IN (%s)
                GROUP BY
                    ttp.account_id
            ),
            player AS(
                SELECT
                    tgp.account_id,
                    SUM(tgp.bronze) AS bronze,
                    SUM(tgp.silver) AS silver,
                    SUM(tgp.gold) AS gold,
                    SUM(tgp.platinum) AS platinum,
                    SUM(tgp.bronze) * 15 + SUM(tgp.silver) * 30 + SUM(tgp.gold) * 90 AS score,
                    child_players.last_updated_date
                FROM
                    trophy_group_player tgp
                JOIN child_players ON child_players.account_id = tgp.account_id
                WHERE
                    tgp.np_communication_id = :np_communication_id
                GROUP BY
                    tgp.account_id,
                    child_players.last_updated_date
            )
            SELECT
                *
            FROM
                (
                SELECT
                    :np_communication_id,
                    player.account_id,
                    player.bronze,
                    player.silver,
                    player.gold,
                    player.platinum,
                    CASE
                        WHEN :max_score = 0 THEN 0
                        WHEN player.score = 0 THEN 0
                        ELSE IFNULL(
                            GREATEST(
                                FLOOR(
                                    IF(
                                        (player.score / :max_score) * 100 = 100 AND :platinum = 1 AND player.platinum = 0,
                                        99,
                                        (player.score / :max_score) * 100
                                    )
                                ),
                                1
                            ),
                            0
                        )
                    END AS progress,
                    player.last_updated_date
                FROM
                    player
            ) AS NEW
            ON DUPLICATE KEY
            UPDATE
                bronze = NEW.bronze,
                silver = NEW.silver,
                gold = NEW.gold,
                platinum = NEW.platinum,
                progress = NEW.progress,
                last_updated_date = IF(
                    NEW.last_updated_date > trophy_title_player.last_updated_date,
                    NEW.last_updated_date,
                    trophy_title_player.last_updated_date
                )
SQL
        ,
            $childListSql
        );
        $query = $this->database->prepare($playerInsertSql);
        $query->bindValue(':np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':max_score', $trophyTitle['max_score'], PDO::PARAM_INT);
        $query->bindValue(':platinum', $trophyTitle['platinum'], PDO::PARAM_INT);
        foreach ($childNpCommunicationIds as $index => $childNpCommunicationId) {
            $query->bindValue(':child_np_' . $index, $childNpCommunicationId, PDO::PARAM_STR);
        }
        $query->execute();

        $playerZeroSql = sprintf(
            <<<'SQL'
            INSERT IGNORE
            INTO trophy_title_player(
                np_communication_id,
                account_id,
                bronze,
                silver,
                gold,
                platinum,
                progress,
                last_updated_date
            ) WITH player AS(
                SELECT
                    ttp.account_id,
                    MAX(ttp.last_updated_date) AS last_updated_date,
                    SUM(ttp.bronze + ttp.silver + ttp.gold + ttp.platinum) AS trophy_total
                FROM
                    trophy_title_player ttp
                WHERE
                    ttp.np_communication_id IN (%s)
                GROUP BY
                    ttp.account_id
                HAVING
                    trophy_total = 0
            )
            SELECT
                :np_communication_id,
                player.account_id,
                0,
                0,
                0,
                0,
                0,
                player.last_updated_date
            FROM
                player
SQL
        ,
            $childListSql
        );
        $query = $this->database->prepare($playerZeroSql);
        $query->bindValue(':np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        foreach ($childNpCommunicationIds as $index => $childNpCommunicationId) {
            $query->bindValue(':child_np_' . $index, $childNpCommunicationId, PDO::PARAM_STR);
        }
        $query->execute();
    }

    /**
     * @return list<string>
     */
    private function getMergeChildrenByParent(string $parentNpCommunicationId): array
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
     * @return array{parent_np_communication_id:string, child_np_communication_ids:list<string>}
     */
    private function getMergeParentAndChildren(string $childNpCommunicationId): array
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
            $parentNpCommunicationId = $parentIds[0];
        } else {
            $parentNpCommunicationId = $this->resolveMergeParent($childNpCommunicationId, $parentIds);
        }

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

        return [
            'parent_np_communication_id' => $parentNpCommunicationId,
            'child_np_communication_ids' => $childNpCommunicationIds,
        ];
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

        return $parentIds[0];
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

    private function insertTrophyMergeMappingFromIds(int $childTrophyId, int $parentTrophyId): void
    {
        $this->executeTransaction(function () use ($childTrophyId, $parentTrophyId): void {
            $query = $this->database->prepare(
                <<<'SQL'
                INSERT IGNORE
                into   trophy_merge
                       (
                              child_np_communication_id,
                              child_group_id,
                              child_order_id,
                              parent_np_communication_id,
                              parent_group_id,
                              parent_order_id
                       )
                SELECT child.np_communication_id,
                       child.group_id,
                       child.order_id,
                       parent.np_communication_id,
                       parent.group_id,
                       parent.order_id
                FROM   trophy child,
                       trophy parent
                WHERE  child.id = :child_trophy_id
                AND    parent.id = :parent_trophy_id
SQL
            );
            $query->bindValue(':child_trophy_id', $childTrophyId, PDO::PARAM_INT);
            $query->bindValue(':parent_trophy_id', $parentTrophyId, PDO::PARAM_INT);
            $query->execute();
        });
    }

    private function markGameAsMergedByNpId(string $npCommunicationId): void
    {
        $this->executeTransaction(function () use ($npCommunicationId): void {
            $query = $this->database->prepare(
                <<<'SQL'
                UPDATE trophy_title_meta
                SET    status = 2
                WHERE  np_communication_id = :child_np_communication_id
                SQL
            );
            $query->bindValue(':child_np_communication_id', $npCommunicationId, PDO::PARAM_STR);
            $query->execute();
        });
    }

    private function markGameAsMergedById(int $gameId): void
    {
        $this->executeTransaction(function () use ($gameId): void {
            $lookup = $this->database->prepare(
                <<<'SQL'
                SELECT np_communication_id
                FROM   trophy_title
                WHERE  id = :game_id
                SQL
            );
            $lookup->bindValue(':game_id', $gameId, PDO::PARAM_INT);
            $lookup->execute();

            $npCommunicationId = $lookup->fetchColumn();

            if ($npCommunicationId === false || $npCommunicationId === null) {
                return;
            }

            $query = $this->database->prepare(
                <<<'SQL'
                UPDATE trophy_title_meta
                SET    status = 2
                WHERE  np_communication_id = :np_communication_id
                SQL
            );
            $query->bindValue(':np_communication_id', (string) $npCommunicationId, PDO::PARAM_STR);
            $query->execute();
        });
    }

    private function copyMergedTrophies(string $childNpCommunicationId, ?TrophyMergeProgressListener $progressListener = null): void
    {
        $countQuery = $this->database->prepare(
            <<<'SQL'
            SELECT
                COUNT(*)
            FROM
                trophy_merge
            WHERE
                child_np_communication_id = :child_np_communication_id
SQL
        );
        $countQuery->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $countQuery->execute();

        $total = (int) $countQuery->fetchColumn();

        if ($total === 0) {
            $this->notifyProgress($progressListener, 73, 'No merged trophies to copy.');

            return;
        }

        $this->notifyProgress(
            $progressListener,
            73,
            sprintf('Found %d merged trophies to copy…', $total)
        );

        $insertMissingParentEarned = $this->database->prepare(
            <<<'SQL'
            INSERT INTO trophy_earned (
                np_communication_id,
                group_id,
                order_id,
                account_id,
                earned_date,
                progress,
                earned
            )
            SELECT
                tm.parent_np_communication_id,
                tm.parent_group_id,
                tm.parent_order_id,
                child.account_id,
                child.earned_date,
                child.progress,
                child.earned
            FROM trophy_merge AS tm
            JOIN trophy_earned AS child ON child.np_communication_id = tm.child_np_communication_id
                AND child.group_id = tm.child_group_id
                AND child.order_id = tm.child_order_id
            LEFT JOIN trophy_earned AS parent ON parent.np_communication_id = tm.parent_np_communication_id
                AND parent.group_id = tm.parent_group_id
                AND parent.order_id = tm.parent_order_id
                AND parent.account_id = child.account_id
            WHERE tm.child_np_communication_id = :child_np_communication_id
              AND parent.account_id IS NULL
SQL
        );
        $insertMissingParentEarned->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $insertMissingParentEarned->execute();

        $synchronizeExistingEarned = $this->database->prepare(
            <<<'SQL'
            UPDATE trophy_earned AS parent
            JOIN trophy_merge AS tm ON tm.parent_np_communication_id = parent.np_communication_id
                AND tm.parent_group_id = parent.group_id
                AND tm.parent_order_id = parent.order_id
            JOIN trophy_earned AS child ON child.np_communication_id = tm.child_np_communication_id
                AND child.group_id = tm.child_group_id
                AND child.order_id = tm.child_order_id
                AND child.account_id = parent.account_id
            SET
                parent.earned_date = CASE
                    WHEN parent.earned_date IS NULL THEN child.earned_date
                    WHEN child.earned_date IS NULL THEN parent.earned_date
                    WHEN child.earned_date < parent.earned_date THEN child.earned_date
                    ELSE parent.earned_date
                END,
                parent.progress = CASE
                    WHEN parent.progress IS NULL THEN child.progress
                    WHEN child.progress IS NULL THEN parent.progress
                    WHEN child.progress > parent.progress THEN child.progress
                    ELSE parent.progress
                END,
                parent.earned = CASE
                    WHEN child.earned = 1 THEN 1
                    ELSE parent.earned
                END
            WHERE tm.child_np_communication_id = :child_np_communication_id
SQL
        );
        $synchronizeExistingEarned->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $synchronizeExistingEarned->execute();

        $this->notifyProgress(
            $progressListener,
            75,
            sprintf('Copying merged trophies… (%d/%d)', $total, $total)
        );
    }

    private function updateParentRelationship(string $childNpCommunicationId, string $parentNpCommunicationId): void
    {
        $query = $this->database->prepare(
            "UPDATE trophy_title_meta SET parent_np_communication_id = :parent_np_communication_id WHERE np_communication_id = :np_communication_id"
        );
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->execute();

        if ($query->rowCount() === 0) {
            $metaExists = $this->database->prepare(
                'SELECT 1 FROM trophy_title_meta WHERE np_communication_id = :np_communication_id'
            );
            $metaExists->bindValue(':np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
            $metaExists->execute();

            if ($metaExists->fetchColumn() === false) {
                $metaInsert = $this->database->prepare(
                    <<<'SQL'
                    INSERT INTO trophy_title_meta (
                        np_communication_id,
                        message,
                        parent_np_communication_id,
                        status
                    ) VALUES (
                        :np_communication_id,
                        '',
                        :parent_np_communication_id,
                        2
                    )
SQL
                );
                $metaInsert->bindValue(':np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
                $metaInsert->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
                $metaInsert->execute();
            }
        }

        $this->updateParentPlatform($parentNpCommunicationId, $childNpCommunicationId);
    }

    private function updateParentPlatform(string $parentNpCommunicationId, string $childNpCommunicationId): void
    {
        $parentPlatforms = $this->getPlatformsByNpCommunicationId($parentNpCommunicationId);
        $childPlatforms = $this->getPlatformsByNpCommunicationId($childNpCommunicationId);

        if ($childPlatforms === []) {
            return;
        }

        $platformLookup = [];
        foreach ($parentPlatforms as $platform) {
            if ($platform === '') {
                continue;
            }

            $platformLookup[$platform] = true;
        }

        $updated = false;

        foreach ($childPlatforms as $platform) {
            if ($platform === '') {
                continue;
            }

            if (!isset($platformLookup[$platform])) {
                $platformLookup[$platform] = true;
                $updated = true;
            }
        }

        if (!$updated) {
            return;
        }

        $sortedPlatforms = $this->sortPlatforms(array_keys($platformLookup));

        $query = $this->database->prepare(
            'UPDATE trophy_title SET platform = :platform WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':platform', implode(',', $sortedPlatforms), PDO::PARAM_STR);
        $query->bindValue(':np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }

    private function getPlatformsByNpCommunicationId(string $npCommunicationId): array
    {
        $query = $this->database->prepare(
            'SELECT platform FROM trophy_title WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $platforms = $query->fetchColumn();

        if ($platforms === false || $platforms === null || $platforms === '') {
            return [];
        }

        $platforms = array_map('trim', explode(',', (string) $platforms));
        $platforms = array_filter($platforms, static fn(string $platform): bool => $platform !== '');

        return array_values($platforms);
    }

    private function sortPlatforms(array $platforms): array
    {
        $order = array_flip(self::PLATFORM_ORDER);

        usort(
            $platforms,
            static function (string $left, string $right) use ($order): int {
                $leftOrder = $order[$left] ?? PHP_INT_MAX;
                $rightOrder = $order[$right] ?? PHP_INT_MAX;

                if ($leftOrder === $rightOrder) {
                    return strcmp($left, $right);
                }

                return $leftOrder <=> $rightOrder;
            }
        );

        return $platforms;
    }

    private function logChange(string $changeType, int $param1, int $param2): void
    {
        $query = $this->database->prepare(
            "INSERT INTO `psn100_change` (`change_type`, `param_1`, `param_2`) VALUES (:change_type, :param_1, :param_2)"
        );
        $query->bindValue(':change_type', $changeType, PDO::PARAM_STR);
        $query->bindValue(':param_1', $param1, PDO::PARAM_INT);
        $query->bindValue(':param_2', $param2, PDO::PARAM_INT);
        $query->execute();
    }

    private function normalizeTrophyName(string $name): string
    {
        $name = trim($name);

        return mb_strtolower($name, 'UTF-8');
    }

    private function insertMappingsByName(int $childGameId, int $parentGameId): string
    {
        $message = '';

        $childTrophies = $this->database->prepare(
            <<<'SQL'
            SELECT np_communication_id,
                   group_id,
                   order_id,
                   name
            FROM   trophy
            WHERE  np_communication_id = (SELECT np_communication_id
                                          FROM   trophy_title
                                          WHERE  id = :child_game_id)
SQL
        );
        $childTrophies->bindValue(':child_game_id', $childGameId, PDO::PARAM_INT);
        $childTrophies->execute();

        $parentTrophies = $this->database->prepare(
            <<<'SQL'
            SELECT np_communication_id,
                   group_id,
                   order_id,
                   name
            FROM   trophy
            WHERE  np_communication_id = (SELECT np_communication_id
                                          FROM   trophy_title
                                          WHERE  id = :parent_game_id)
SQL
        );
        $parentTrophies->bindValue(':parent_game_id', $parentGameId, PDO::PARAM_INT);
        $parentTrophies->execute();

        $parentTrophyByName = [];

        while ($parentTrophy = $parentTrophies->fetch(PDO::FETCH_ASSOC)) {
            $name = $this->normalizeTrophyName((string) $parentTrophy['name']);
            $parentTrophyByName[$name][] = $parentTrophy;
        }

        while ($childTrophy = $childTrophies->fetch(PDO::FETCH_ASSOC)) {
            $childName = $this->normalizeTrophyName((string) $childTrophy['name']);
            $parentTrophy = $parentTrophyByName[$childName] ?? [];

            if (count($parentTrophy) === 1) {
                $this->insertDirectMapping($childTrophy, $parentTrophy[0]);
            } else {
                $message .= $childTrophy['name'] . " couldn't be merged.<br>";
            }
        }

        return $message;
    }

    private function insertMappingsByIcon(int $childGameId, int $parentGameId): string
    {
        $message = '';

        $childTrophies = $this->database->prepare(
            <<<'SQL'
            SELECT
                t.np_communication_id,
                t.group_id,
                t.order_id,
                t.name,
                t.icon_url,
                tc.counter
            FROM
                trophy t,
                (
                SELECT
                    icon_url,
                    COUNT(icon_url) AS counter
                FROM
                    trophy
                WHERE
                    np_communication_id =(
                    SELECT
                        np_communication_id
                    FROM
                        trophy_title
                    WHERE
                        id = :child_game_id
                )
            GROUP BY
                icon_url
            ) AS tc
            WHERE
                t.icon_url = tc.icon_url AND t.np_communication_id =(
                SELECT
                    np_communication_id
                FROM
                    trophy_title
                WHERE
                    id = :child_game_id
            );
SQL
        );
        $childTrophies->bindValue(':child_game_id', $childGameId, PDO::PARAM_INT);
        $childTrophies->execute();

        while ($childTrophy = $childTrophies->fetch(PDO::FETCH_ASSOC)) {
            $parentTrophies = $this->database->prepare(
                <<<'SQL'
                SELECT np_communication_id,
                       group_id,
                       order_id
                FROM   trophy
                WHERE  np_communication_id = (SELECT np_communication_id
                                              FROM   trophy_title
                                              WHERE  id = :parent_game_id)
                       AND icon_url = :icon_url
SQL
            );
            $parentTrophies->bindValue(':parent_game_id', $parentGameId, PDO::PARAM_INT);
            $parentTrophies->bindValue(':icon_url', $childTrophy['icon_url'], PDO::PARAM_STR);
            $parentTrophies->execute();

            $parentTrophy = $parentTrophies->fetchAll(PDO::FETCH_ASSOC);

            if ((int) $childTrophy['counter'] === 1 && count($parentTrophy) === 1) {
                $this->insertDirectMapping($childTrophy, $parentTrophy[0]);
            } else {
                $message .= $childTrophy['name'] . " couldn't be merged.<br>";
            }
        }

        return $message;
    }

    private function insertMappingsByOrder(int $childGameId, int $parentGameId): void
    {
        $query = $this->database->prepare(
            <<<'SQL'
            INSERT IGNORE
            into   trophy_merge
                   (
                          child_np_communication_id,
                          child_group_id,
                          child_order_id,
                          parent_np_communication_id,
                          parent_group_id,
                          parent_order_id
                   )
            SELECT     child.np_communication_id,
                       child.group_id,
                       child.order_id,
                       parent.np_communication_id,
                       parent.group_id,
                       parent.order_id
            FROM       trophy child
            INNER JOIN trophy parent
            USING      (group_id, order_id)
            WHERE      child.np_communication_id =
                       (
                              SELECT np_communication_id
                              FROM   trophy_title
                              WHERE  id = :child_game_id)
            AND        parent.np_communication_id =
                       (
                              SELECT np_communication_id
                              FROM   trophy_title
                              WHERE  id = :parent_game_id)
SQL
        );
        $query->bindValue(':child_game_id', $childGameId, PDO::PARAM_INT);
        $query->bindValue(':parent_game_id', $parentGameId, PDO::PARAM_INT);
        $query->execute();
    }

    private function insertDirectMapping(array $childTrophy, array $parentTrophy): void
    {
        $query = $this->database->prepare(
            <<<'SQL'
            INSERT IGNORE
            into   trophy_merge
                   (
                          child_np_communication_id,
                          child_group_id,
                          child_order_id,
                          parent_np_communication_id,
                          parent_group_id,
                          parent_order_id
                   )
                   VALUES
                   (
                          :child_np_communication_id,
                          :child_group_id,
                          :child_order_id,
                          :parent_np_communication_id,
                          :parent_group_id,
                          :parent_order_id
                   )
SQL
        );
        $query->bindValue(':child_np_communication_id', $childTrophy['np_communication_id'], PDO::PARAM_STR);
        $query->bindValue(':child_group_id', $childTrophy['group_id'], PDO::PARAM_STR);
        $query->bindValue(':child_order_id', (int) $childTrophy['order_id'], PDO::PARAM_INT);
        $query->bindValue(':parent_np_communication_id', $parentTrophy['np_communication_id'], PDO::PARAM_STR);
        $query->bindValue(':parent_group_id', $parentTrophy['group_id'], PDO::PARAM_STR);
        $query->bindValue(':parent_order_id', (int) $parentTrophy['order_id'], PDO::PARAM_INT);
        $query->execute();
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

    private function getGameIdByNpCommunicationId(string $npCommunicationId): int
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT id
            FROM   trophy_title
            WHERE  np_communication_id = :np_communication_id
SQL
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $gameId = $query->fetchColumn();

        if ($gameId === false) {
            throw new RuntimeException('Unable to locate trophy title for copy operation.');
        }

        return (int) $gameId;
    }

    private function cloneGameHistory(int $sourceGameId, int $cloneGameId): void
    {
        $historyQuery = $this->database->prepare(
            <<<'SQL'
            SELECT id,
                   detail,
                   icon_url,
                   set_version,
                   discovered_at
            FROM   trophy_title_history
            WHERE  trophy_title_id = :game_id
            ORDER BY id
SQL
        );
        $historyQuery->bindValue(':game_id', $sourceGameId, PDO::PARAM_INT);
        $historyQuery->execute();

        $historyInsert = $this->database->prepare(
            <<<'SQL'
            INSERT INTO trophy_title_history (
                trophy_title_id,
                detail,
                icon_url,
                set_version,
                discovered_at
            )
            VALUES (
                :clone_game_id,
                :detail,
                :icon_url,
                :set_version,
                :discovered_at
            )
SQL
        );

        $groupHistoryInsert = $this->database->prepare(
            <<<'SQL'
            INSERT INTO trophy_group_history (
                title_history_id,
                group_id,
                name,
                detail,
                icon_url
            )
            SELECT :new_history_id,
                   group_id,
                   name,
                   detail,
                   icon_url
            FROM   trophy_group_history
            WHERE  title_history_id = :old_history_id
SQL
        );

        $trophyHistoryInsert = $this->database->prepare(
            <<<'SQL'
            INSERT INTO trophy_history (
                title_history_id,
                group_id,
                order_id,
                name,
                detail,
                icon_url,
                progress_target_value
            )
            SELECT :new_history_id,
                   group_id,
                   order_id,
                   name,
                   detail,
                   icon_url,
                   progress_target_value
            FROM   trophy_history
            WHERE  title_history_id = :old_history_id
SQL
        );

        while ($historyRow = $historyQuery->fetch(PDO::FETCH_ASSOC)) {
            $historyInsert->execute([
                ':clone_game_id' => $cloneGameId,
                ':detail' => $historyRow['detail'],
                ':icon_url' => $historyRow['icon_url'],
                ':set_version' => $historyRow['set_version'],
                ':discovered_at' => $historyRow['discovered_at'],
            ]);

            $newHistoryId = (int) $this->database->lastInsertId();
            $oldHistoryId = (int) $historyRow['id'];

            $groupHistoryInsert->execute([
                ':new_history_id' => $newHistoryId,
                ':old_history_id' => $oldHistoryId,
            ]);

            $trophyHistoryInsert->execute([
                ':new_history_id' => $newHistoryId,
                ':old_history_id' => $oldHistoryId,
            ]);
        }

        $historyQuery->closeCursor();
    }

    private function executeTransaction(callable $operation): void
    {
        $this->beginTransaction();

        try {
            $operation();
            $this->commitTransaction();
        } catch (Throwable $exception) {
            $this->rollBackTransaction();
            throw $exception;
        }
    }

    private function beginTransaction(): void
    {
        if (!$this->database->inTransaction()) {
            $this->database->beginTransaction();
        }
    }

    private function commitTransaction(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->commit();
        }
    }

    private function rollBackTransaction(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }

    private function notifyProgress(?TrophyMergeProgressListener $listener, int $percent, string $message): void
    {
        if ($listener === null) {
            return;
        }

        $listener->onProgress($percent, $message);
    }
}
