<?php

declare(strict_types=1);

require_once __DIR__ . '/Admin/GameCopyService.php';
require_once __DIR__ . '/Admin/TrophyMergeProgressListener.php';
require_once __DIR__ . '/NestedDatabaseTransactionRunner.php';
require_once __DIR__ . '/TrophyMergeEarnedCopier.php';
require_once __DIR__ . '/TrophyMergeMappingService.php';
require_once __DIR__ . '/TrophyMergeMetadataRepository.php';
require_once __DIR__ . '/TrophyMergeMethod.php';
require_once __DIR__ . '/TrophyMergePlayerProgressUpdater.php';
require_once __DIR__ . '/TrophyTitleCloneService.php';

class TrophyMergeService
{
    private readonly NestedDatabaseTransactionRunner $transactionRunner;

    private ?TrophyMergeEarnedCopier $earnedCopier = null;

    private ?TrophyMergeMappingService $mappingService = null;

    private ?TrophyMergeMetadataRepository $metadataRepository = null;

    private ?TrophyMergePlayerProgressUpdater $playerProgressUpdater = null;

    private ?TrophyTitleCloneService $cloneService = null;

    public function __construct(
        private readonly PDO $database,
        ?NestedDatabaseTransactionRunner $transactionRunner = null,
        ?TrophyMergeEarnedCopier $earnedCopier = null,
    ) {
        $this->transactionRunner = $transactionRunner ?? new NestedDatabaseTransactionRunner($database);
        $this->earnedCopier = $earnedCopier;
    }

    public function mergeSpecificTrophies(int $parentTrophyId, array $childTrophyIds): string
    {
        if ($childTrophyIds === []) {
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
                $this->metadataRepository()->markGameAsMergedByNpId($childTrophy['np_communication_id']);

                $childGameId = $this->getGameIdByTrophyId($childTrophyId);

                $this->earnedCopier()->copyTrophyMapping(
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
        TrophyMergeMethod|string $method,
        ?TrophyMergeProgressListener $progressListener = null
    ): string
    {
        $method = TrophyMergeMethod::fromMixed($method);
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
            $this->notifyProgress($progressListener, 30, $method->progressLabel());

            match ($method) {
                TrophyMergeMethod::Order => $this->mappingService()->insertMappingsByOrder(
                    $childGameId,
                    $parentGameId
                ),
                TrophyMergeMethod::Name => $message .= $this->mappingService()->insertMappingsByName(
                    $childGameId,
                    $parentGameId
                ),
                TrophyMergeMethod::Icon => $message .= $this->mappingService()->insertMappingsByIcon(
                    $childGameId,
                    $parentGameId
                ),
            };

            $this->notifyProgress($progressListener, 55, 'Trophy mappings saved.');
            $this->notifyProgress($progressListener, 60, 'Preparing to mark child game as merged…');
            $this->notifyProgress($progressListener, 62, 'Marking child game as merged…');
            $this->metadataRepository()->markGameAsMergedById($childGameId);
            $this->notifyProgress($progressListener, 65, 'Child game marked as merged.');
            $this->notifyProgress($progressListener, 70, 'Preparing to copy merged trophies…');
            $this->notifyProgress($progressListener, 72, 'Copying merged trophies…');
            $this->earnedCopier()->copyMergedTrophies($childNpCommunicationId, $progressListener);
            $this->notifyProgress($progressListener, 75, 'Merged trophies copied.');
            $this->notifyProgress($progressListener, 80, 'Updating player trophy groups…');
            $this->updateTrophyGroupPlayer($childGameId);
            $this->notifyProgress($progressListener, 85, 'Player trophy groups updated.');
            $this->notifyProgress($progressListener, 88, 'Updating player trophy titles…');
            $this->updateTrophyTitlePlayer($childGameId);
            $this->notifyProgress($progressListener, 92, 'Player trophy titles updated.');
            $this->notifyProgress($progressListener, 94, 'Updating parent relationship…');
            $this->metadataRepository()->updateParentRelationship($childNpCommunicationId, $parentNpCommunicationId);
            $this->notifyProgress($progressListener, 96, 'Parent relationship updated.');
            $this->notifyProgress($progressListener, 98, 'Logging merge activity…');
            $this->metadataRepository()->logChange('GAME_MERGE', $childGameId, $parentGameId);
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
            SELECT tt.id
            FROM trophy_title tt
            INNER JOIN trophy t ON t.np_communication_id = tt.np_communication_id
            WHERE t.id = :child_trophy_id
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

    private function earnedCopier(): TrophyMergeEarnedCopier
    {
        return $this->earnedCopier ??= new TrophyMergeEarnedCopier($this->database);
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

    private function metadataRepository(): TrophyMergeMetadataRepository
    {
        return $this->metadataRepository ??= new TrophyMergeMetadataRepository(
            $this->database,
            $this->transactionRunner
        );
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
