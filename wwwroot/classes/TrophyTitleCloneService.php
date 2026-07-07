<?php

declare(strict_types=1);

require_once __DIR__ . '/NestedDatabaseTransactionRunner.php';

/**
 * Clones a trophy title into a new MERGE_* title, including catalog rows and history.
 *
 * Encapsulates clone persistence that was previously embedded in TrophyMergeService.
 */
final class TrophyTitleCloneService
{
    public function __construct(
        private readonly PDO $database,
        private readonly NestedDatabaseTransactionRunner $transactionRunner,
    ) {
    }

    /**
     * @return array{clone_game_id:int, clone_np_communication_id:string}
     */
    public function cloneFromGameId(int $childGameId): array
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

        $this->transactionRunner->execute(function () use (
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

        $this->logClone($childGameId, $cloneGameId);

        return [
            'clone_game_id' => $cloneGameId,
            'clone_np_communication_id' => $cloneNpCommunicationId,
        ];
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

    private function logClone(int $childGameId, int $cloneGameId): void
    {
        $query = $this->database->prepare(
            "INSERT INTO `psn100_change` (`change_type`, `param_1`, `param_2`) VALUES (:change_type, :param_1, :param_2)"
        );
        $query->bindValue(':change_type', 'GAME_CLONE', PDO::PARAM_STR);
        $query->bindValue(':param_1', $childGameId, PDO::PARAM_INT);
        $query->bindValue(':param_2', $cloneGameId, PDO::PARAM_INT);
        $query->execute();
    }
}
