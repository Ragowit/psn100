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
            $trophyGroups[$trophy['groupKey']] = [
                'np_communication_id' => $trophy['np_communication_id'],
                'group_id' => $trophy['group_id'],
            ];
            $trophyTitles[$trophy['np_communication_id']] = $trophy['np_communication_id'];
        }

        foreach ($trophyGroups as $group) {
            $this->recalculateGroup($group['np_communication_id'], $group['group_id']);
        }

        foreach ($trophyTitles as $npCommunicationId) {
            $this->recalculateTitle($npCommunicationId, $status);
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

            $query = $this->database->prepare('UPDATE trophy SET status = :status WHERE id = :trophy_id');
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

    private function recalculateGroup(string $npCommunicationId, string $groupId): void
    {
        $this->executeGroupStatement(
            <<<'SQL'
WITH bronze AS (
    SELECT
      COUNT(*) AS count
    FROM
      trophy
    WHERE
      np_communication_id = :np_communication_id
      AND group_id = :group_id
      AND status = 0
      AND type = 'bronze'
  ),
  silver AS (
    SELECT
      COUNT(*) AS count
    FROM
      trophy
    WHERE
      np_communication_id = :np_communication_id
      AND group_id = :group_id
      AND status = 0
      AND type = 'silver'
  ),
  gold AS (
    SELECT
      COUNT(*) AS count
    FROM
      trophy
    WHERE
      np_communication_id = :np_communication_id
      AND group_id = :group_id
      AND status = 0
      AND type = 'gold'
  ),
  platinum AS (
    SELECT
      COUNT(*) AS count
    FROM
      trophy
    WHERE
      np_communication_id = :np_communication_id
      AND group_id = :group_id
      AND status = 0
      AND type = 'platinum'
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

        foreach ([
            'bronze' => 'bronze',
            'silver' => 'silver',
            'gold' => 'gold',
            'platinum' => 'platinum',
        ] as $type => $column) {
            $this->executeGroupStatement($this->getPlayerTrophyCountSql($type, $column), $npCommunicationId, $groupId);
        }

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

    private function getPlayerTrophyCountSql(string $type, string $column): string
    {
        return sprintf(
            <<<'SQL'
WITH player_trophy_count AS(
    SELECT
        account_id,
        COUNT(type) AS count
    FROM
        trophy_earned te
        LEFT JOIN trophy t ON t.np_communication_id = te.np_communication_id
        AND t.group_id = te.group_id
        AND t.order_id = te.order_id
        AND t.status = 0
        AND t.type = '%1$s'
    WHERE
        te.np_communication_id = :np_communication_id
        AND te.group_id = :group_id
    GROUP BY
        account_id
    )
    UPDATE
        trophy_group_player tgp,
        player_trophy_count ptc
    SET
        tgp.%2$s = ptc.count
    WHERE
        tgp.np_communication_id = :np_communication_id
        AND tgp.group_id = :group_id
        AND tgp.account_id = ptc.account_id
SQL,
            $type,
            $column
        );
    }

    private function recalculateTitle(string $npCommunicationId, int $status): void
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
        }
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
