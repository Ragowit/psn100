<?php

declare(strict_types=1);

class TrophyStatusUpdateResult
{
    /** @var string[] */
    private array $trophyNames;

    private string $statusText;

    /**
     * @param string[] $trophyNames
     */
    public function __construct(array $trophyNames, string $statusText)
    {
        $this->trophyNames = $trophyNames;
        $this->statusText = $statusText;
    }

    /**
     * @return string[]
     */
    public function getTrophyNames(): array
    {
        return $this->trophyNames;
    }

    public function getStatusText(): string
    {
        return $this->statusText;
    }

    public function toHtml(): string
    {
        $html = '<p>';

        foreach ($this->trophyNames as $trophyName) {
            $html .= 'Trophy ID ' . htmlspecialchars($trophyName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br>';
        }

        $html .= 'is now set as ' . htmlspecialchars($this->statusText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';

        return $html;
    }
}

class TrophyStatusService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    /**
     * @return int[]
     */
    public function parseTrophyIds(string $input): array
    {
        $values = preg_split('/[\s,]+/', trim($input));
        $ids = [];

        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (!ctype_digit($value)) {
                throw new InvalidArgumentException('Invalid trophy ID: ' . $value);
            }

            $ids[] = (int) $value;
        }

        $ids = array_values(array_unique($ids));

        if (count($ids) === 0) {
            throw new InvalidArgumentException('No trophies were provided.');
        }

        return $ids;
    }

    /**
     * @return int[]
     */
    public function getTrophyIdsForGame(int $gameId): array
    {
        $query = $this->database->prepare(
            'SELECT id FROM trophy WHERE np_communication_id = (SELECT np_communication_id FROM trophy_title WHERE id = :id)'
        );
        $query->bindValue(':id', $gameId, PDO::PARAM_INT);
        $query->execute();

        $trophies = $query->fetchAll(PDO::FETCH_COLUMN);

        if ($trophies === false || count($trophies) === 0) {
            throw new InvalidArgumentException('No trophies found for the selected game.');
        }

        $ids = array_map('intval', $trophies);

        return array_values(array_unique($ids));
    }

    /**
     * @param int[] $trophyIds
     */
    public function updateTrophies(array $trophyIds, int $status): TrophyStatusUpdateResult
    {
        $trophyIds = array_values(array_unique(array_map('intval', $trophyIds)));

        if (count($trophyIds) === 0) {
            throw new InvalidArgumentException('No trophies were provided.');
        }

        $trophyNames = [];
        $trophyGroups = [];
        $trophyTitles = [];

        foreach ($trophyIds as $trophyId) {
            $trophy = $this->updateTrophyStatus((int) $trophyId, $status);
            $trophyNames[] = $trophy['label'];
            if (!isset($trophyGroups[$trophy['groupKey']])) {
                $trophyGroups[$trophy['groupKey']] = [
                    'np_communication_id' => $trophy['np_communication_id'],
                    'group_id' => $trophy['group_id'],
                    'trophy_ids' => [],
                ];
            }
            $trophyGroups[$trophy['groupKey']]['trophy_ids'][] = $trophy['id'];
            $trophyTitles[$trophy['np_communication_id']][] = $trophy['id'];
        }

        foreach ($trophyGroups as $group) {
            $this->recalculateGroup($group['np_communication_id'], $group['group_id'], $group['trophy_ids']);
        }

        foreach ($trophyTitles as $npCommunicationId => $titleTrophyIds) {
            $this->recalculateTitle((string) $npCommunicationId, $status, $titleTrophyIds);
        }

        $statusText = $status === 1 ? 'unobtainable' : 'obtainable';

        return new TrophyStatusUpdateResult($trophyNames, $statusText);
    }

    /**
     * @return array{id: int, name: string, np_communication_id: string, group_id: string, label: string, groupKey: string}
     */
    private function updateTrophyStatus(int $trophyId, int $status): array
    {
        try {
            $this->database->beginTransaction();

            $query = $this->database->prepare('UPDATE trophy_meta SET status = :status WHERE trophy_id = :trophy_id');
            $query->bindValue(':status', $status, PDO::PARAM_INT);
            $query->bindValue(':trophy_id', $trophyId, PDO::PARAM_INT);
            $query->execute();

            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }

        $query = $this->database->prepare('SELECT np_communication_id, group_id, name FROM trophy WHERE id = :trophy_id');
        $query->bindValue(':trophy_id', $trophyId, PDO::PARAM_INT);
        $query->execute();
        $trophy = $query->fetch(PDO::FETCH_ASSOC);

        if ($trophy === false) {
            throw new RuntimeException('Trophy not found: ' . $trophyId);
        }

        $npCommunicationId = (string) $trophy['np_communication_id'];
        $groupId = (string) $trophy['group_id'];
        $name = (string) $trophy['name'];

        return [
            'id' => $trophyId,
            'name' => $name,
            'np_communication_id' => $npCommunicationId,
            'group_id' => $groupId,
            'label' => $trophyId . ' (' . $name . ')',
            'groupKey' => $npCommunicationId . ',' . $groupId,
        ];
    }

    private function recalculateGroup(string $npCommunicationId, string $groupId, array $affectedTrophyIds): void
    {
        $this->createImpactedAccountsTempTable($npCommunicationId, $groupId, $affectedTrophyIds);

        $this->executeGroupStatement(
            <<<'SQL'
WITH bronze AS (
    SELECT
      COUNT(tm.trophy_id) AS count
    FROM
      trophy t
      JOIN trophy_meta tm ON tm.trophy_id = t.id AND tm.status = 0
    WHERE
      t.np_communication_id = :np_communication_id
      AND t.group_id = :group_id
      AND t.type = 'bronze'
  ),
  silver AS (
    SELECT
      COUNT(tm.trophy_id) AS count
    FROM
      trophy t
      JOIN trophy_meta tm ON tm.trophy_id = t.id AND tm.status = 0
    WHERE
      t.np_communication_id = :np_communication_id
      AND t.group_id = :group_id
      AND t.type = 'silver'
  ),
  gold AS (
    SELECT
      COUNT(tm.trophy_id) AS count
    FROM
      trophy t
      JOIN trophy_meta tm ON tm.trophy_id = t.id AND tm.status = 0
    WHERE
      t.np_communication_id = :np_communication_id
      AND t.group_id = :group_id
      AND t.type = 'gold'
  ),
  platinum AS (
    SELECT
      COUNT(tm.trophy_id) AS count
    FROM
      trophy t
      JOIN trophy_meta tm ON tm.trophy_id = t.id AND tm.status = 0
    WHERE
      t.np_communication_id = :np_communication_id
      AND t.group_id = :group_id
      AND t.type = 'platinum'
  )
  UPDATE
    trophy_group tg,
    bronze b,
    silver s,
    gold g,
    platinum p
  SET
    tg.bronze = b.count,
    tg.silver = s.count,
    tg.gold = g.count,
    tg.platinum = p.count
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
        trophy_group_player tgp,
        max_score ms,
        user_score us
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
        AND tgp.account_id = us.account_id
        AND EXISTS (
            SELECT 1
            FROM temp_impacted_accounts tia
            WHERE tia.account_id = tgp.account_id
        )
SQL,
            $npCommunicationId,
            $groupId
        );
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
            COALESCE(SUM(t.type = 'bronze'), 0) AS bronze,
            COALESCE(SUM(t.type = 'silver'), 0) AS silver,
            COALESCE(SUM(t.type = 'gold'), 0) AS gold,
            COALESCE(SUM(t.type = 'platinum'), 0) AS platinum
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

    private function recalculateTitle(string $npCommunicationId, int $status, array $affectedTrophyIds): void
    {
        $this->createImpactedAccountsTempTable($npCommunicationId, null, $affectedTrophyIds);

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
    trophy_title tt,
    trophy_group_count tgc
  SET
    tt.bronze = tgc.bronze,
    tt.silver = tgc.silver,
    tt.gold = tgc.gold,
    tt.platinum = tgc.platinum
  WHERE
    np_communication_id = :np_communication_id
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
        trophy_title_player ttp,
        player_trophy_count ptc
    SET
        ttp.bronze = ptc.bronze,
        ttp.silver = ptc.silver,
        ttp.gold = ptc.gold,
        ttp.platinum = ptc.platinum
    WHERE
        ttp.account_id = ptc.account_id
        AND ttp.np_communication_id = :np_communication_id
        AND EXISTS (
            SELECT 1
            FROM temp_impacted_accounts tia
            WHERE tia.account_id = ttp.account_id
        )
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
        trophy_title_player ttp,
        user_score us
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
        AND ttp.account_id = us.account_id
        AND EXISTS (
            SELECT 1
            FROM temp_impacted_accounts tia
            WHERE tia.account_id = ttp.account_id
        )
SQL
        );
        $statement->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':max_score', $maxScore, PDO::PARAM_INT);
        $statement->execute();

        $gameId = $this->findGameId($npCommunicationId);

        if ($gameId !== null) {
            $changeType = $status === 1 ? 'GAME_UNOBTAINABLE' : 'GAME_OBTAINABLE';
            $statement = $this->database->prepare(
                'INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES (:change_type, :param_1)'
            );
            $statement->bindValue(':change_type', $changeType, PDO::PARAM_STR);
            $statement->bindValue(':param_1', $gameId, PDO::PARAM_INT);
            $statement->execute();

            $statement = $this->database->prepare(
                'INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES (\'GAME_TROPHY_RECONCILE\', :param_1)'
            );
            $statement->bindValue(':param_1', $gameId, PDO::PARAM_INT);
            $statement->execute();
        }
    }

    private function createImpactedAccountsTempTable(string $npCommunicationId, ?string $groupId, array $affectedTrophyIds): void
    {
        $this->database->exec('DROP TEMPORARY TABLE IF EXISTS temp_impacted_accounts');
        $this->database->exec('CREATE TEMPORARY TABLE temp_impacted_accounts (account_id BIGINT UNSIGNED PRIMARY KEY) ENGINE=MEMORY');

        if (count($affectedTrophyIds) === 0) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($affectedTrophyIds), '?'));
        $sql = 'INSERT IGNORE INTO temp_impacted_accounts (account_id)
            SELECT DISTINCT te.account_id
            FROM trophy_earned te
            INNER JOIN trophy t ON t.np_communication_id = te.np_communication_id
                AND t.group_id = te.group_id
                AND t.order_id = te.order_id
            WHERE te.np_communication_id = ?
              AND te.earned = 1';

        if ($groupId !== null) {
            $sql .= ' AND te.group_id = ?';
        }

        $sql .= ' AND t.id IN (' . $placeholders . ')';

        $statement = $this->database->prepare($sql);
        $parameterIndex = 1;
        $statement->bindValue($parameterIndex++, $npCommunicationId, PDO::PARAM_STR);
        if ($groupId !== null) {
            $statement->bindValue($parameterIndex++, $groupId, PDO::PARAM_STR);
        }
        foreach ($affectedTrophyIds as $trophyId) {
            $statement->bindValue($parameterIndex++, (int) $trophyId, PDO::PARAM_INT);
        }
        $statement->execute();
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
