<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerScanTitleMetadataHelper.php';

/**
 * Persists player trophy earned/progress rows during scans and propagates state to
 * merge-parent trophies when mappings exist.
 *
 * Encapsulates trophy_earned upsert logic that was previously embedded in ThirtyMinuteCronJob.
 */
final class PlayerEarnedTrophyPersister
{
    private readonly PlayerScanTitleMetadataHelper $titleMetadataHelper;

    public function __construct(
        private readonly PDO $database,
        ?PlayerScanTitleMetadataHelper $titleMetadataHelper = null,
    ) {
        $this->titleMetadataHelper = $titleMetadataHelper ?? new PlayerScanTitleMetadataHelper();
    }

    public function persistEarnedTrophy(
        string $npCommunicationId,
        string $groupId,
        int $orderId,
        string $accountId,
        bool $earned,
        string $rawProgress,
        string $rawEarnedDateTime,
    ): void {
        $earnedDate = $rawEarnedDateTime === ''
            ? null
            : $this->titleMetadataHelper->formatDateTimeForDatabase($rawEarnedDateTime);
        $progress = $rawProgress === '' ? null : (int) $rawProgress;

        $this->upsertChildEarnedTrophy(
            $npCommunicationId,
            $groupId,
            $orderId,
            $accountId,
            $earnedDate,
            $progress,
            $earned,
        );

        $parent = $this->findMergeParent($npCommunicationId, $groupId, $orderId);
        if ($parent === null) {
            return;
        }

        $this->upsertParentEarnedTrophy(
            $parent['parent_np_communication_id'],
            $parent['parent_group_id'],
            (int) $parent['parent_order_id'],
            $accountId,
            $earnedDate,
            $progress,
            $earned,
        );
    }

    private function upsertChildEarnedTrophy(
        string $npCommunicationId,
        string $groupId,
        int $orderId,
        string $accountId,
        ?string $earnedDate,
        ?int $progress,
        bool $earned,
    ): void {
        $query = $this->database->prepare("INSERT INTO trophy_earned(
                np_communication_id,
                group_id,
                order_id,
                account_id,
                earned_date,
                progress,
                earned
            )
            VALUES(
                :np_communication_id,
                :group_id,
                :order_id,
                :account_id,
                :earned_date,
                :progress,
                :earned
            ) AS new
            ON DUPLICATE KEY
            UPDATE
                earned_date = IF(trophy_earned.earned = 0, new.earned_date, trophy_earned.earned_date),
                progress = new.progress,
                -- earned never goes 1→0; only insert as 0/1 or promote 0→1.
                earned = IF(trophy_earned.earned = 1, trophy_earned.earned, new.earned)");
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        // Account IDs are stored as BIGINT UNSIGNED. Bind as string to avoid
        // truncating larger values when PHP integers overflow.
        $query->bindValue(':account_id', $accountId, PDO::PARAM_STR);
        $query->bindValue(':earned_date', $earnedDate, PDO::PARAM_STR);
        $query->bindValue(':progress', $progress, PDO::PARAM_INT);
        $query->bindValue(':earned', $earned, PDO::PARAM_INT);
        $query->execute();
    }

    /**
     * @return array{parent_np_communication_id: string, parent_group_id: string, parent_order_id: int|string}|null
     */
    private function findMergeParent(string $npCommunicationId, string $groupId, int $orderId): ?array
    {
        $query = $this->database->prepare("SELECT parent_np_communication_id,
                    parent_group_id,
                    parent_order_id
            FROM   trophy_merge
            WHERE  child_np_communication_id = :child_np_communication_id
                    AND child_group_id = :child_group_id
                    AND child_order_id = :child_order_id ");
        $query->bindValue(':child_np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':child_group_id', $groupId, PDO::PARAM_STR);
        $query->bindValue(':child_order_id', $orderId, PDO::PARAM_INT);
        $query->execute();
        $parent = $query->fetch();

        if ($parent === false) {
            return null;
        }

        return $parent;
    }

    private function upsertParentEarnedTrophy(
        string $npCommunicationId,
        string $groupId,
        int $orderId,
        string $accountId,
        ?string $earnedDate,
        ?int $progress,
        bool $earned,
    ): void {
        $query = $this->database->prepare("INSERT INTO trophy_earned(
                np_communication_id,
                group_id,
                order_id,
                account_id,
                earned_date,
                progress,
                earned
            )
            VALUES(
                :np_communication_id,
                :group_id,
                :order_id,
                :account_id,
                :earned_date,
                :progress,
                :earned
            ) AS new
            ON DUPLICATE KEY
            UPDATE
                earned_date = IF(trophy_earned.earned_date < new.earned_date, trophy_earned.earned_date, new.earned_date),
                progress = IF(trophy_earned.progress IS NULL, new.progress,
                    IF(new.progress IS NULL, trophy_earned.progress,
                        IF(trophy_earned.progress > new.progress, trophy_earned.progress, new.progress)
                    )
                ),
                earned = IF(trophy_earned.earned = 1, trophy_earned.earned, new.earned)");
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        // Account IDs are stored as BIGINT UNSIGNED. Bind as string to avoid
        // truncating larger values when PHP integers overflow.
        $query->bindValue(':account_id', $accountId, PDO::PARAM_STR);
        $query->bindValue(':earned_date', $earnedDate, PDO::PARAM_STR);
        $query->bindValue(':progress', $progress, PDO::PARAM_INT);
        $query->bindValue(':earned', $earned, PDO::PARAM_INT);
        $query->execute();
    }
}
