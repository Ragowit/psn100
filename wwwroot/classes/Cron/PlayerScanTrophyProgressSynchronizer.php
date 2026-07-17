<?php

declare(strict_types=1);

require_once __DIR__ . '/../AutomaticTrophyTitleMergeService.php';
require_once __DIR__ . '/../Psn100Logger.php';
require_once __DIR__ . '/PlayerEarnedTrophyPersister.php';

use Tustin\Haste\Exception\NotFoundHttpException;

/**
 * Fetches earned trophy progress for one title during player scans and recalculates
 * player aggregates for the title, its groups, and any merge parents.
 *
 * Encapsulates per-title trophy progress synchronization that was previously embedded in
 * ThirtyMinuteCronJob.
 */
final class PlayerScanTrophyProgressSynchronizer
{
    public function __construct(
        private readonly PDO $database,
        private readonly TrophyCalculator $trophyCalculator,
        private readonly Psn100Logger $logger,
        private readonly PlayerEarnedTrophyPersister $earnedTrophyPersister,
        private readonly AutomaticTrophyTitleMergeService $automaticTrophyTitleMergeService,
    ) {
    }

    /**
     * @param list<string> $mergeParentsToRecompute
     */
    public function synchronizeTrophyProgress(
        object $user,
        object $trophyTitle,
        string $npCommunicationId,
        bool $newTrophies,
        array $mergeParentsToRecompute,
    ): void {
        $trophyGroups = $this->retryNotFound(
            fn () => $trophyTitle->trophyGroups(),
            sprintf('Fetching trophy groups for %s (%s)', $trophyTitle->name(), $npCommunicationId)
        );

        foreach ($trophyGroups as $trophyGroup) {
            $groupTrophies = $this->retryNotFound(
                fn () => $trophyGroup->trophies(),
                sprintf(
                    'Fetching trophies for %s (%s/%s)',
                    $trophyTitle->name(),
                    $npCommunicationId,
                    $trophyGroup->id()
                )
            );

            foreach ($groupTrophies as $trophy) {
                $trophyEarned = $trophy->earned();
                $progress = clone($trophy)->progress();
                if ($trophyEarned || ($progress !== '' && (int) $progress > 0)) {
                    $this->earnedTrophyPersister->persistEarnedTrophy(
                        $npCommunicationId,
                        $trophyGroup->id(),
                        (int) $trophy->id(),
                        (string) $user->accountId(),
                        $trophyEarned,
                        $progress,
                        $trophy->earnedDateTime(),
                    );
                }
            }

            $this->trophyCalculator->recalculateTrophyGroup(
                $npCommunicationId,
                $trophyGroup->id(),
                (string) $user->accountId()
            );
        }

        $this->trophyCalculator->recalculateTrophyTitle(
            $npCommunicationId,
            $trophyTitle->lastUpdatedDateTime(),
            $newTrophies,
            (string) $user->accountId(),
            false
        );

        $query = $this->database->prepare("SELECT DISTINCT parent_np_communication_id,
                                                parent_group_id
                            FROM   trophy_merge
                            WHERE  child_np_communication_id = :child_np_communication_id ");
        $query->bindValue(':child_np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();
        while ($row = $query->fetch()) {
            $this->trophyCalculator->recalculateTrophyGroup(
                $row['parent_np_communication_id'],
                $row['parent_group_id'],
                (string) $user->accountId()
            );
            $this->trophyCalculator->recalculateTrophyTitle(
                $row['parent_np_communication_id'],
                $trophyTitle->lastUpdatedDateTime(),
                false,
                (string) $user->accountId(),
                true
            );
        }

        foreach ($mergeParentsToRecompute as $mergeParent) {
            $this->automaticTrophyTitleMergeService->recomputeMergeProgressByParent($mergeParent);
        }
    }

    /**
     * @template T
     * @param callable():T $operation
     * @return T
     */
    private function retryNotFound(callable $operation, string $description): mixed
    {
        $attempt = 0;
        $delay = 2;
        $maxAttempts = 5;

        while (true) {
            try {
                return $operation();
            } catch (NotFoundHttpException $exception) {
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    $this->logger->log(sprintf(
                        '%s failed with %s after %d attempts. Aborting retries.',
                        $description,
                        $exception->getMessage(),
                        $attempt
                    ));

                    throw $exception;
                }

                sleep($delay);
                $delay = min($delay * 2, 60);
            }
        }
    }
}
