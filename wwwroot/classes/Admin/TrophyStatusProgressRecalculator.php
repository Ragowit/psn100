<?php

declare(strict_types=1);

require_once __DIR__ . '/../TrophyMetaStatus.php';
require_once __DIR__ . '/../ChangelogEntry.php';

/**
 * Recalculates trophy group/title aggregates and player progress after a status change.
 *
 * Encapsulates the SQL previously embedded in TrophyStatusService so that service can
 * focus on flipping trophy_meta.status and collecting affected groups/titles.
 */
class TrophyStatusProgressRecalculator
{
    public function __construct(
        private readonly PDO $database,
    ) {
    }

    /**
     * @param int[] $affectedTrophyIds
     */
    public function recalculateGroup(string $npCommunicationId, string $groupId, array $affectedTrophyIds): void
    {
        $this->createImpactedAccountsTempTable($npCommunicationId, $groupId, $affectedTrophyIds);

        $this->executeGroupStatement(
            <<<'SQL'
WITH counts AS (
    SELECT
      COALESCE(SUM(CASE WHEN t.type = 'bronze' THEN 1 ELSE 0 END), 0) AS bronze,
      COALESCE(SUM(CASE WHEN t.type = 'silver' THEN 1 ELSE 0 END), 0) AS silver,
      COALESCE(SUM(CASE WHEN t.type = 'gold' THEN 1 ELSE 0 END), 0) AS gold,
      COALESCE(SUM(CASE WHEN t.type = 'platinum' THEN 1 ELSE 0 END), 0) AS platinum
    FROM
      trophy t
      JOIN trophy_meta tm ON tm.trophy_id = t.id AND tm.status = 0
    WHERE
      t.np_communication_id = :np_communication_id
      AND t.group_id = :group_id
  )
  UPDATE
    trophy_group tg
    CROSS JOIN counts c
  SET
    tg.bronze = c.bronze,
    tg.silver = c.silver,
    tg.gold = c.gold,
    tg.platinum = c.platinum
  WHERE
    tg.np_communication_id = :np_communication_id
    AND tg.group_id = :group_id
SQL,
            $npCommunicationId,
            $groupId
        );

        $this->executeGroupStatement($this->getPlayerTrophyCountSql(), $npCommunicationId, $groupId);

        $this->executeGroupStatement(
            <<<'SQL'
WITH max_score AS (
    SELECT
        bronze * 15 + silver * 30 + gold * 90 AS points
    FROM
        trophy_group
    WHERE
        np_communication_id = :np_communication_id
        AND group_id = :group_id
    ),
    user_score AS (
        SELECT
            account_id,
            bronze * 15 + silver * 30 + gold * 90 AS points
        FROM
            trophy_group_player
        WHERE
            np_communication_id = :np_communication_id
            AND group_id = :group_id
        GROUP BY
            account_id
    )
    UPDATE
        trophy_group_player tgp
        INNER JOIN user_score us ON us.account_id = tgp.account_id
        CROSS JOIN max_score ms
    SET
        tgp.progress = IF(
            ms.points = 0,
            100,
            IF(
                us.points != 0
                AND FLOOR(us.points / ms.points * 100) = 0,
                1,
                FLOOR(us.points / ms.points * 100)
            )
        )
    WHERE
        tgp.np_communication_id = :np_communication_id
        AND tgp.group_id = :group_id
SQL,
            $npCommunicationId,
            $groupId
        );
    }

    /**
     * @param int[] $affectedTrophyIds
     */
    public function recalculateTitle(string $npCommunicationId, int|TrophyMetaStatus $status, array $affectedTrophyIds): void
    {
        $statement = $this->database->prepare(
            <<<'SQL'
WITH trophy_group_count AS (
    SELECT
      SUM(bronze) AS bronze,
      SUM(silver) AS silver,
      SUM(gold) AS gold,
      SUM(platinum) AS platinum
    FROM
      trophy_group
    WHERE
      np_communication_id = :np_communication_id
  )
  UPDATE
    trophy_title tt
    CROSS JOIN trophy_group_count tgc
  SET
    tt.bronze = tgc.bronze,
    tt.silver = tgc.silver,
    tt.gold = tgc.gold,
    tt.platinum = tgc.platinum
  WHERE
    tt.np_communication_id = :np_communication_id
SQL
        );
        $statement->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $statement->execute();

        $statement = $this->database->prepare(
            <<<'SQL'
WITH player_trophy_count AS (
    SELECT
        account_id,
        IFNULL(SUM(bronze), 0) AS bronze,
        IFNULL(SUM(silver), 0) AS silver,
        IFNULL(SUM(gold), 0) AS gold,
        IFNULL(SUM(platinum), 0) AS platinum
    FROM
        trophy_group_player
    WHERE
        np_communication_id = :np_communication_id
    GROUP BY
        account_id
    )
    UPDATE
        trophy_title_player ttp
        INNER JOIN player_trophy_count ptc ON ptc.account_id = ttp.account_id
    SET
        ttp.bronze = ptc.bronze,
        ttp.silver = ptc.silver,
        ttp.gold = ptc.gold,
        ttp.platinum = ptc.platinum
    WHERE
        ttp.np_communication_id = :np_communication_id
SQL
        );
        $statement->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $statement->execute();

        $statement = $this->database->prepare(
            'SELECT
                bronze * 15 + silver * 30 + gold * 90 AS max_score
            FROM
                trophy_title
            WHERE
                np_communication_id = :np_communication_id'
        );
        $statement->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $statement->execute();
        $maxScore = $statement->fetchColumn();
        $maxScore = $maxScore === false ? 0 : (int) $maxScore;

        $statement = $this->database->prepare(
            <<<'SQL'
WITH user_score AS (
    SELECT
        account_id,
        bronze * 15 + silver * 30 + gold * 90 AS points
    FROM
        trophy_title_player
    WHERE
        np_communication_id = :np_communication_id
    GROUP BY
        account_id
    )
    UPDATE
        trophy_title_player ttp
        INNER JOIN user_score us ON us.account_id = ttp.account_id
    SET
        ttp.progress = IF(
            :max_score = 0,
            100,
            IF(
                us.points != 0
                AND FLOOR(us.points / :max_score * 100) = 0,
                1,
                FLOOR(us.points / :max_score * 100)
            )
        )
    WHERE
        ttp.np_communication_id = :np_communication_id
SQL
        );
        $statement->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':max_score', $maxScore, PDO::PARAM_INT);
        $statement->execute();

        $gameId = $this->findGameId($npCommunicationId);

        if ($gameId !== null) {
            $changeType = TrophyMetaStatus::fromMixed($status)->changeType();
            $statement = $this->database->prepare(
                'INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES (:change_type, :param_1)'
            );
            $statement->bindValue(':change_type', $changeType->value, PDO::PARAM_STR);
            $statement->bindValue(':param_1', $gameId, PDO::PARAM_INT);
            $statement->execute();
        }
    }

    private function executeGroupStatement(string $sql, string $npCommunicationId, string $groupId): void
    {
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $statement->execute();
    }

    private function getPlayerTrophyCountSql(): string
    {
        return <<<'SQL'
UPDATE
    trophy_group_player tgp
    LEFT JOIN (
        SELECT
            tia.account_id,
            COALESCE(SUM(CASE WHEN tm.trophy_id IS NOT NULL AND t.type = 'bronze' THEN 1 ELSE 0 END), 0) AS bronze,
            COALESCE(SUM(CASE WHEN tm.trophy_id IS NOT NULL AND t.type = 'silver' THEN 1 ELSE 0 END), 0) AS silver,
            COALESCE(SUM(CASE WHEN tm.trophy_id IS NOT NULL AND t.type = 'gold' THEN 1 ELSE 0 END), 0) AS gold,
            COALESCE(SUM(CASE WHEN tm.trophy_id IS NOT NULL AND t.type = 'platinum' THEN 1 ELSE 0 END), 0) AS platinum
        FROM
            temp_impacted_accounts tia
            LEFT JOIN trophy_earned te ON te.account_id = tia.account_id
            AND te.np_communication_id = :np_communication_id
            AND te.group_id = :group_id
            AND te.earned = 1
            LEFT JOIN trophy t ON t.np_communication_id = te.np_communication_id
            AND t.group_id = te.group_id
            AND t.order_id = te.order_id
            LEFT JOIN trophy_meta tm ON tm.trophy_id = t.id
            AND tm.status = 0
        GROUP BY
            tia.account_id
    ) aggregate ON aggregate.account_id = tgp.account_id
SET
    tgp.bronze = COALESCE(aggregate.bronze, 0),
    tgp.silver = COALESCE(aggregate.silver, 0),
    tgp.gold = COALESCE(aggregate.gold, 0),
    tgp.platinum = COALESCE(aggregate.platinum, 0)
WHERE
    tgp.np_communication_id = :np_communication_id
    AND tgp.group_id = :group_id
    AND aggregate.account_id IS NOT NULL
SQL;
    }

    /**
     * @param int[] $affectedTrophyIds
     */
    private function createImpactedAccountsTempTable(string $npCommunicationId, ?string $groupId, array $affectedTrophyIds): void
    {
        $this->database->exec('DROP TEMPORARY TABLE IF EXISTS temp_impacted_accounts');
        $this->database->exec('CREATE TEMPORARY TABLE temp_impacted_accounts (account_id BIGINT UNSIGNED PRIMARY KEY)');

        if ($affectedTrophyIds === []) {
            return;
        }

        $orderIds = $this->resolveOrderIds($affectedTrophyIds);
        if ($orderIds === []) {
            return;
        }

        // Resolve order_ids from the small trophy table first, then drive from
        // trophy_title_player so trophy_earned is probed by account_id (HASH
        // partition key) instead of scanning all partitions by title.
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = 'INSERT IGNORE INTO temp_impacted_accounts (account_id)
            SELECT DISTINCT ttp.account_id
            FROM trophy_title_player ttp
            JOIN trophy_earned te
              ON te.account_id = ttp.account_id
             AND te.np_communication_id = ttp.np_communication_id
             AND te.order_id IN (' . $placeholders . ')
             AND te.earned = 1
            WHERE ttp.np_communication_id = ?';

        if ($groupId !== null) {
            $sql .= ' AND te.group_id = ?';
        }

        $statement = $this->database->prepare($sql);
        $parameterIndex = 1;
        foreach ($orderIds as $orderId) {
            $statement->bindValue($parameterIndex++, $orderId, PDO::PARAM_INT);
        }
        $statement->bindValue($parameterIndex++, $npCommunicationId, PDO::PARAM_STR);
        if ($groupId !== null) {
            $statement->bindValue($parameterIndex++, $groupId, PDO::PARAM_STR);
        }
        $statement->execute();
    }

    /**
     * @param int[] $affectedTrophyIds
     * @return list<int>
     */
    private function resolveOrderIds(array $affectedTrophyIds): array
    {
        $placeholders = implode(',', array_fill(0, count($affectedTrophyIds), '?'));
        $statement = $this->database->prepare(
            'SELECT DISTINCT order_id FROM trophy WHERE id IN (' . $placeholders . ')'
        );
        foreach (array_values($affectedTrophyIds) as $index => $trophyId) {
            $statement->bindValue($index + 1, (int) $trophyId, PDO::PARAM_INT);
        }
        $statement->execute();

        $orderIds = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $orderId) {
            $orderIds[] = (int) $orderId;
        }

        return $orderIds;
    }

    private function findGameId(string $npCommunicationId): ?int
    {
        $statement = $this->database->prepare('SELECT id FROM trophy_title WHERE np_communication_id = :np_communication_id');
        $statement->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $statement->execute();
        $gameId = $statement->fetchColumn();

        if ($gameId === false) {
            return null;
        }

        return (int) $gameId;
    }
}
