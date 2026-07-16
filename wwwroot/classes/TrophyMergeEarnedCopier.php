<?php

declare(strict_types=1);

require_once __DIR__ . '/Admin/TrophyMergeProgressListener.php';

/**
 * Copies player trophy-earned progress from child titles into merge parents.
 *
 * Previously embedded in TrophyMergeService.
 */
final class TrophyMergeEarnedCopier
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function copyTrophyMapping(
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
                    trophy_title_player AS ttp
                JOIN trophy_earned AS child
                    ON child.account_id = ttp.account_id
                    AND child.np_communication_id = :child_np_communication_id
                    AND child.order_id = :child_order_id
                LEFT JOIN trophy_earned AS existing ON existing.np_communication_id = :parent_np_communication_id
                    AND existing.group_id = :parent_group_id
                    AND existing.order_id = :parent_order_id
                    AND existing.account_id = child.account_id
                WHERE
                    ttp.np_communication_id = :child_np_communication_id
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

    public function copyMergedTrophies(
        string $childNpCommunicationId,
        ?TrophyMergeProgressListener $progressListener = null
    ): void {
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

        // Drive child trophy_earned via trophy_title_player.account_id so
        // HASH(account_id) partition pruning applies on the large earned table.
        $mergeSourceCte = <<<'SQL'
            WITH merge_source AS (
                SELECT
                    tm.parent_np_communication_id,
                    tm.parent_group_id,
                    tm.parent_order_id,
                    child.account_id,
                    child.earned_date,
                    child.progress,
                    child.earned
                FROM trophy_merge AS tm
                JOIN trophy_title_player AS ttp
                    ON ttp.np_communication_id = tm.child_np_communication_id
                JOIN trophy_earned AS child
                    ON child.account_id = ttp.account_id
                    AND child.np_communication_id = tm.child_np_communication_id
                    AND child.group_id = tm.child_group_id
                    AND child.order_id = tm.child_order_id
                WHERE tm.child_np_communication_id = :child_np_communication_id
            )
        SQL;

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
        SQL
            . "\n" . $mergeSourceCte . "\n"
            . <<<'SQL'
            SELECT
                source.parent_np_communication_id,
                source.parent_group_id,
                source.parent_order_id,
                source.account_id,
                source.earned_date,
                source.progress,
                source.earned
            FROM merge_source AS source
            LEFT JOIN trophy_earned AS parent ON parent.np_communication_id = source.parent_np_communication_id
                AND parent.group_id = source.parent_group_id
                AND parent.order_id = source.parent_order_id
                AND parent.account_id = source.account_id
            WHERE parent.account_id IS NULL
SQL
        );
        $insertMissingParentEarned->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $insertMissingParentEarned->execute();

        $synchronizeExistingEarned = $this->database->prepare(
            $mergeSourceCte . <<<'SQL'
            UPDATE trophy_earned AS parent
            JOIN merge_source AS source ON source.parent_np_communication_id = parent.np_communication_id
                AND source.parent_group_id = parent.group_id
                AND source.parent_order_id = parent.order_id
                AND source.account_id = parent.account_id
            SET
                parent.earned_date = CASE
                    WHEN parent.earned_date IS NULL THEN source.earned_date
                    WHEN source.earned_date IS NULL THEN parent.earned_date
                    WHEN source.earned_date < parent.earned_date THEN source.earned_date
                    ELSE parent.earned_date
                END,
                parent.progress = CASE
                    WHEN parent.progress IS NULL THEN source.progress
                    WHEN source.progress IS NULL THEN parent.progress
                    WHEN source.progress > parent.progress THEN source.progress
                    ELSE parent.progress
                END,
                parent.earned = CASE
                    WHEN source.earned = 1 THEN 1
                    ELSE parent.earned
                END
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

    private function notifyProgress(?TrophyMergeProgressListener $listener, int $percent, string $message): void
    {
        if ($listener === null) {
            return;
        }

        $listener->onProgress($percent, $message);
    }
}
