<?php

declare(strict_types=1);

require_once __DIR__ . '/Psn100Logger.php';

class TrophyHistoryRecorder
{
    private PDO $database;

    private ?Psn100Logger $logger;

    public function __construct(PDO $database, ?Psn100Logger $logger = null)
    {
        $this->database = $database;
        $this->logger = $logger;
    }

    public function recordByTitleId(int $titleId): void
    {
        $startedTransaction = false;

        try {
            if (!$this->database->inTransaction()) {
                $this->database->beginTransaction();
                $startedTransaction = true;
            }

            $titleQuery = $this->database->prepare(
                'SELECT np_communication_id, detail, icon_url, set_version FROM trophy_title WHERE id = :title_id'
            );
            $titleQuery->bindValue(':title_id', $titleId, PDO::PARAM_INT);
            $titleQuery->execute();

            $titleRow = $titleQuery->fetch(PDO::FETCH_ASSOC);

            if ($titleRow === false) {
                if ($startedTransaction) {
                    $this->database->rollBack();
                }

                return;
            }

            $titleHistoryStatement = $this->database->prepare(
                'INSERT INTO trophy_title_history (trophy_title_id, detail, icon_url, set_version) VALUES (:title_id, :detail, :icon_url, :set_version)'
            );
            $titleHistoryStatement->bindValue(':title_id', $titleId, PDO::PARAM_INT);
            $titleHistoryStatement->bindValue(':detail', $titleRow['detail'], PDO::PARAM_STR);
            $titleHistoryStatement->bindValue(':icon_url', $titleRow['icon_url'], PDO::PARAM_STR);
            $titleHistoryStatement->bindValue(':set_version', $titleRow['set_version'], PDO::PARAM_STR);
            $titleHistoryStatement->execute();

            $titleHistoryId = (int) $this->database->lastInsertId();

            if ($this->logger !== null) {
                $this->logger->log(sprintf(
                    'Recorded new trophy_title_history entry %d for trophy_title.id %d',
                    $titleHistoryId,
                    $titleId
                ));
            }

            $this->recordHistorySnapshotChange($titleId);

            $groupSelect = $this->database->prepare(
                'SELECT group_id, name, detail, icon_url FROM trophy_group WHERE np_communication_id = :np_communication_id'
            );
            $groupSelect->bindValue(':np_communication_id', $titleRow['np_communication_id'], PDO::PARAM_STR);
            $groupSelect->execute();

            $groupInsert = $this->database->prepare(
                'INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (:title_history_id, :group_id, :name, :detail, :icon_url)'
            );

            while ($groupRow = $groupSelect->fetch(PDO::FETCH_ASSOC)) {
                $groupInsert->bindValue(':title_history_id', $titleHistoryId, PDO::PARAM_INT);
                $groupInsert->bindValue(':group_id', $groupRow['group_id'], PDO::PARAM_STR);
                $groupInsert->bindValue(':name', $groupRow['name'], PDO::PARAM_STR);
                $groupInsert->bindValue(':detail', $groupRow['detail'], PDO::PARAM_STR);
                $groupInsert->bindValue(':icon_url', $groupRow['icon_url'], PDO::PARAM_STR);
                $groupInsert->execute();
            }

            $trophySelect = $this->database->prepare(
                'SELECT group_id, order_id, name, detail, icon_url, progress_target_value FROM trophy WHERE np_communication_id = :np_communication_id'
            );
            $trophySelect->bindValue(':np_communication_id', $titleRow['np_communication_id'], PDO::PARAM_STR);
            $trophySelect->execute();

            $trophyInsert = $this->database->prepare(
                'INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (:title_history_id, :group_id, :order_id, :name, :detail, :icon_url, :progress_target_value)'
            );

            while ($trophyRow = $trophySelect->fetch(PDO::FETCH_ASSOC)) {
                $trophyInsert->bindValue(':title_history_id', $titleHistoryId, PDO::PARAM_INT);
                $trophyInsert->bindValue(':group_id', $trophyRow['group_id'], PDO::PARAM_STR);
                $trophyInsert->bindValue(':order_id', $trophyRow['order_id'], PDO::PARAM_INT);
                $trophyInsert->bindValue(':name', $trophyRow['name'], PDO::PARAM_STR);
                $trophyInsert->bindValue(':detail', $trophyRow['detail'], PDO::PARAM_STR);
                $trophyInsert->bindValue(':icon_url', $trophyRow['icon_url'], PDO::PARAM_STR);

                if ($trophyRow['progress_target_value'] === null) {
                    $trophyInsert->bindValue(':progress_target_value', null, PDO::PARAM_NULL);
                } else {
                    $trophyInsert->bindValue(':progress_target_value', (int) $trophyRow['progress_target_value'], PDO::PARAM_INT);
                }

                $trophyInsert->execute();
            }

            if ($startedTransaction) {
                $this->database->commit();
            }
        } catch (\Throwable $exception) {
            if ($startedTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }

            if ($this->logger !== null) {
                $this->logger->log(sprintf(
                    'Failed to record trophy history snapshot for title ID %d: %s',
                    $titleId,
                    $exception->getMessage()
                ));
            }
        }
    }

    private function recordHistorySnapshotChange(int $titleId): void
    {
        $changeStatement = $this->database->prepare(
            "INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_HISTORY_SNAPSHOT', :title_id)"
        );
        $changeStatement->bindValue(':title_id', $titleId, PDO::PARAM_INT);
        $changeStatement->execute();
    }
}
