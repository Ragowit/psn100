<?php

declare(strict_types=1);

require_once __DIR__ . '/Admin/GameCopyService.php';
require_once __DIR__ . '/Admin/TrophyMergeProgressListener.php';
require_once __DIR__ . '/NestedDatabaseTransactionRunner.php';
require_once __DIR__ . '/TrophyMergeMappingService.php';
require_once __DIR__ . '/TrophyMergePlayerProgressUpdater.php';
require_once __DIR__ . '/TrophyTitleCloneService.php';

class TrophyMergeService
{
    private const PLATFORM_ORDER = ['PS3', 'PSVITA', 'PS4', 'PSVR', 'PS5', 'PSVR2', 'PC'];

    private PDO $database;

    private NestedDatabaseTransactionRunner $transactionRunner;

    private ?TrophyMergeMappingService $mappingService = null;

    private ?TrophyMergePlayerProgressUpdater $playerProgressUpdater = null;

    private ?TrophyTitleCloneService $cloneService = null;

    public function __construct(PDO $database, ?NestedDatabaseTransactionRunner $transactionRunner = null)
    {
        $this->database = $database;
        $this->transactionRunner = $transactionRunner ?? new NestedDatabaseTransactionRunner($database);
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

        $childTrophies = [];

        foreach ($childTrophyIds as $childTrophyId) {
            $childTrophyId = (int) $childTrophyId;
            $childTrophy = $this->getTrophyById($childTrophyId);

            if (str_starts_with($childTrophy['np_communication_id'], 'MERGE')) {
                throw new InvalidArgumentException("Child can't be a merge title.");
            }

            $childTrophies[] = [
                'id' => $childTrophyId,
                'trophy' => $childTrophy,
            ];
        }

        $this->transactionRunner->execute(function () use ($parentTrophy, $parentTrophyId, $childTrophies): void {
            foreach ($childTrophies as $childData) {
                $childTrophyId = $childData['id'];
                $childTrophy = $childData['trophy'];

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
        });

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

        $this->transactionRunner->execute(function () use (
            $childGameId,
            $childNpCommunicationId,
            $parentGameId,
            $parentNpCommunicationId,
            $method,
            $progressListener,
            &$message
        ): void {
            switch ($method) {
                case 'name':
                    $this->notifyProgress($progressListener, 30, 'Matching trophies by name…');
                    $message .= $this->mappingService()->insertMappingsByName($childGameId, $parentGameId);
                    break;
                case 'icon':
                    $this->notifyProgress($progressListener, 30, 'Matching trophies by icon…');
                    $message .= $this->mappingService()->insertMappingsByIcon($childGameId, $parentGameId);
                    break;
                case 'order':
                    $this->notifyProgress($progressListener, 30, 'Matching trophies by list order…');
                    $this->mappingService()->insertMappingsByOrder($childGameId, $parentGameId);
                    break;
                default:
                    throw new InvalidArgumentException('Wrong input');
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
        });

        return $message . 'The games have been merged.';
    }

    public function cloneGame(int $childGameId): string
    {
        $this->cloneService()->cloneFromGameId($childGameId);

        return 'The game have been cloned.';
    }

    /**
     * @return array{clone_game_id:int, clone_np_communication_id:string}
     */
    public function cloneGameWithInfo(int $childGameId): array
    {
        return $this->cloneService()->cloneFromGameId($childGameId);
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
        $this->playerProgressUpdater()->recomputeByParent($parentNpCommunicationId);
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
        $this->playerProgressUpdater()->updateTrophyGroupPlayer($childGameId);
    }

    private function updateTrophyTitlePlayer(int $childGameId): void
    {
        $this->playerProgressUpdater()->updateTrophyTitlePlayer($childGameId);
    }

    private function mappingService(): TrophyMergeMappingService
    {
        return $this->mappingService ??= new TrophyMergeMappingService($this->database);
    }

    private function playerProgressUpdater(): TrophyMergePlayerProgressUpdater
    {
        return $this->playerProgressUpdater ??= new TrophyMergePlayerProgressUpdater($this->database);
    }

    private function cloneService(): TrophyTitleCloneService
    {
        return $this->cloneService ??= new TrophyTitleCloneService($this->database, $this->transactionRunner);
    }

    private function insertTrophyMergeMappingFromIds(int $childTrophyId, int $parentTrophyId): void
    {
        $this->transactionRunner->execute(function () use ($childTrophyId, $parentTrophyId): void {
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
        $this->transactionRunner->execute(function () use ($npCommunicationId): void {
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
        $this->transactionRunner->execute(function () use ($gameId): void {
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
                JOIN trophy_earned AS child ON child.np_communication_id = tm.child_np_communication_id
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

    private function notifyProgress(?TrophyMergeProgressListener $listener, int $percent, string $message): void
    {
        if ($listener === null) {
            return;
        }

        $listener->onProgress($percent, $message);
    }
}
