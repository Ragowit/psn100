<?php

declare(strict_types=1);

require_once __DIR__ . '/../AutomaticTrophyTitleMergeService.php';
require_once __DIR__ . '/../TrophyHistoryRecorder.php';
require_once __DIR__ . '/../TrophyMergeService.php';
require_once __DIR__ . '/PlayerScanCatalogSideEffectResult.php';
require_once __DIR__ . '/PlayerScanNewTitleMergeHandler.php';

/**
 * Applies post-catalog side effects after trophy rows are synchronized during player scans.
 *
 * Encapsulates history recording, automatic merge triggers, and changelog insertion that
 * were previously embedded in PlayerScanTitleCatalogSynchronizer.
 */
final class PlayerScanCatalogSideEffects
{
    public function __construct(
        private readonly PDO $database,
        private readonly ?TrophyHistoryRecorder $historyRecorder = null,
        private readonly ?PlayerScanNewTitleMergeHandler $newTitleMergeHandler = null,
    ) {
    }

    public function applyAfterCatalogSync(
        string $npCommunicationId,
        bool $titleDataChanged,
        bool $groupDataChanged,
        bool $trophyDataChanged,
        bool $isNewTitle,
        bool $newTrophies,
        ?int $titleId = null,
    ): PlayerScanCatalogSideEffectResult {
        if ($titleDataChanged || $groupDataChanged || $trophyDataChanged) {
            $titleId = $this->resolveTitleId($npCommunicationId, $titleId);

            if ($titleId !== null) {
                $this->historyRecorder()->recordByTitleId($titleId);
            }
        }

        $mergeParentsToRecompute = [];
        if ($isNewTitle) {
            $mergeParentsToRecompute = $this->newTitleMergeHandler()->handleNewTitle($npCommunicationId);
        }

        if ($newTrophies) {
            $titleId = $this->resolveTitleId($npCommunicationId, $titleId);

            if ($titleId !== null) {
                $this->insertGameVersionChangelog($titleId);
            }
        }

        return PlayerScanCatalogSideEffectResult::create($titleId, $mergeParentsToRecompute);
    }

    private function resolveTitleId(string $npCommunicationId, ?int $titleId): ?int
    {
        if ($titleId !== null) {
            return $titleId;
        }

        return $this->findTrophyTitleId($npCommunicationId);
    }

    private function findTrophyTitleId(string $npCommunicationId): ?int
    {
        $query = $this->database->prepare(
            'SELECT id FROM trophy_title WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $id = $query->fetchColumn();

        if ($id === false) {
            return null;
        }

        return (int) $id;
    }

    private function insertGameVersionChangelog(int $titleId): void
    {
        $query = $this->database->prepare(
            "INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_VERSION', :param_1)"
        );
        $query->bindValue(':param_1', $titleId, PDO::PARAM_INT);
        $query->execute();
    }

    private function historyRecorder(): TrophyHistoryRecorder
    {
        return $this->historyRecorder ?? new TrophyHistoryRecorder($this->database);
    }

    private function newTitleMergeHandler(): PlayerScanNewTitleMergeHandler
    {
        return $this->newTitleMergeHandler
            ?? new AutomaticTrophyTitleMergeService($this->database, new TrophyMergeService($this->database));
    }
}
