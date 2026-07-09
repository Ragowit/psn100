<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyMergeRelationshipResolver.php';

class TrophyMergePlayerProgressUpdater
{
    private readonly TrophyMergeRelationshipResolver $relationshipResolver;

    public function __construct(
        private readonly PDO $database,
        ?TrophyMergeRelationshipResolver $relationshipResolver = null,
    ) {
        $this->relationshipResolver = $relationshipResolver ?? new TrophyMergeRelationshipResolver($database);
    }

    public function updateTrophyGroupPlayer(int $childGameId): void
    {
        $childNpCommunicationId = $this->getGameNpCommunicationId($childGameId);

        $mergeData = $this->getMergeParentAndChildren($childNpCommunicationId);
        $parentNpCommunicationId = $mergeData['parent_np_communication_id'];
        $childNpCommunicationIds = array_values($mergeData['child_np_communication_ids']);

        $this->updateTrophyGroupPlayerForMerge($parentNpCommunicationId, $childNpCommunicationIds);
    }

    public function updateTrophyTitlePlayer(int $childGameId): void
    {
        $childNpCommunicationId = $this->getGameNpCommunicationId($childGameId);

        $mergeData = $this->getMergeParentAndChildren($childNpCommunicationId);
        $parentNpCommunicationId = $mergeData['parent_np_communication_id'];
        $childNpCommunicationIds = array_values($mergeData['child_np_communication_ids']);

        $this->updateTrophyTitlePlayerForMerge($parentNpCommunicationId, $childNpCommunicationIds);
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

        $this->updateTrophyGroupPlayerForMerge($parentNpCommunicationId, $childNpCommunicationIds);
        $this->updateTrophyTitlePlayerForMerge($parentNpCommunicationId, $childNpCommunicationIds);
    }

    /**
     * @return array{parent_np_communication_id:string, child_np_communication_ids:list<string>}
     */
    public function getMergeParentAndChildren(string $childNpCommunicationId): array
    {
        return $this->relationshipResolver->getMergeParentAndChildren($childNpCommunicationId);
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
                            tg.max_score = 0,
                            100,
                            IF(
                                player.score = 0,
                                0,
                                IFNULL(
                                    GREATEST(
                                        FLOOR(
                                            IF(
                                                (player.score / tg.max_score) * 100 = 100 AND tg.platinum = 1 AND player.platinum = 0,
                                                99,
                                                (player.score / tg.max_score) * 100
                                            )
                                        ),
                                        1
                                    ),
                                    0
                                )
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
                        WHEN :max_score = 0 THEN 100
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
