<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerScanTrophyTitleLoopResult.php';
require_once __DIR__ . '/PlayerScanTitleMetadataHelper.php';
require_once __DIR__ . '/PlayerScanTitleCatalogSynchronizer.php';
require_once __DIR__ . '/PlayerScanTrophyProgressSynchronizer.php';
require_once __DIR__ . '/PlayerScanStaleGameDeletionService.php';
require_once __DIR__ . '/PlayerScanCompletionService.php';
require_once __DIR__ . '/PlayerScanTrophyTitleRefresher.php';
require_once __DIR__ . '/WorkerScanCoordinator.php';

/**
 * Fetches and synchronizes a player's trophy titles during a worker scan.
 *
 * Encapsulates the per-title iteration, retry guards, and stale-game cleanup that
 * were previously embedded in ThirtyMinuteCronJob.
 */
final class PlayerScanTrophyTitleLoop
{
    private const DEFAULT_SLEEPER = 'sleep';

    private readonly \Closure $sleeper;

    public function __construct(
        private readonly PDO $database,
        private readonly Psn100Logger $logger,
        private readonly WorkerScanCoordinator $workerScanCoordinator,
        private readonly PlayerScanTitleMetadataHelper $titleMetadataHelper,
        private readonly PlayerScanTitleCatalogSynchronizer $titleCatalogSynchronizer,
        private readonly PlayerScanTrophyProgressSynchronizer $trophyProgressSynchronizer,
        private readonly PlayerScanStaleGameDeletionService $staleGameDeletionService,
        private readonly PlayerScanCompletionService $scanCompletionService,
        private readonly PlayerScanTrophyTitleRefresher $trophyTitleRefresher,
        ?callable $sleeper = null,
    ) {
        $this->sleeper = \Closure::fromCallable($sleeper ?? self::DEFAULT_SLEEPER);
    }

    /**
     * @param array<string, mixed> $player
     * @param array<string, mixed> $worker
     * @param array<string, bool> $missingGameDeletionCheck
     * @param array<string, bool> $missingTrophyTitleRetry
     * @param array<string, bool> $trophyTitleCountRetry
     * @param array<string, bool> $invalidTitleDateRetry
     */
    public function processAccessibleTrophyTitles(
        object $client,
        object $user,
        array $player,
        array $worker,
        string $onlineId,
        string &$recheck,
        array &$missingGameDeletionCheck,
        array &$missingTrophyTitleRetry,
        array &$trophyTitleCountRetry,
        array &$invalidTitleDateRetry,
    ): PlayerScanTrophyTitleLoopResult {
        $totalTrophiesStart = $user->trophySummary()->platinum()
            + $user->trophySummary()->gold()
            + $user->trophySummary()->silver()
            + $user->trophySummary()->bronze();

        $query = $this->database->prepare("SELECT np_communication_id,
                last_updated_date
            FROM   trophy_title_player
            WHERE  account_id = :account_id AND np_communication_id LIKE 'N%'");
        $query->bindValue(':account_id', (string) $user->accountId(), PDO::PARAM_STR);
        $query->execute();
        $gameLastUpdatedDate = $query->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->workerScanCoordinator->setWaitingScanProgress(
            (int) $worker['id'],
            sprintf('Fetching game list for %s.', $onlineId)
        );

        $trophyTitleFetchCompleted = false;
        try {
            $trophyTitleCollection = $user->trophyTitles();
            $trophyTitles = iterator_to_array($trophyTitleCollection->getIterator());
        } catch (TypeError) {
            ($this->sleeper)(5);

            return PlayerScanTrophyTitleLoopResult::continueLoop();
        } catch (Exception $exception) {
            // Transient PSN/network failures (e.g. cURL error 18 during paginated
            // trophyTitles fetches) should retry instead of crashing the worker.
            $this->logger->log(sprintf(
                'Failed to fetch trophy titles for %s: %s. Waiting 1 minute before retrying.',
                $onlineId,
                $exception->getMessage()
            ));
            $this->workerScanCoordinator->setWaitingScanProgress(
                (int) $worker['id'],
                'Encountered a problem while fetching game list. Waiting 1 minute before retrying.'
            );
            ($this->sleeper)(60);

            return PlayerScanTrophyTitleLoopResult::continueLoop();
        }

        $trophyTitleFetchCompleted = true;
        $psnGameCount = count($trophyTitles);
        $localGameCount = count($gameLastUpdatedDate);
        $gameCountDelta = $psnGameCount - $localGameCount;

        if ($gameCountDelta <= -50) {
            if (!($trophyTitleCountRetry[$onlineId] ?? false)) {
                $trophyTitleCountRetry[$onlineId] = true;

                $this->workerScanCoordinator->setWaitingScanProgress(
                    (int) $worker['id'],
                    'Trophy title count lower than expected. Waiting 1 minute before retrying.'
                );

                ($this->sleeper)(60 * 1);
                $recheck = '';

                return PlayerScanTrophyTitleLoopResult::continueLoop();
            }
        }

        usort(
            $trophyTitles,
            function ($left, $right): int {
                $leftTimestamp = strtotime($left->lastUpdatedDateTime());
                $rightTimestamp = strtotime($right->lastUpdatedDateTime());

                if ($leftTimestamp === false || $rightTimestamp === false) {
                    return strcmp($left->lastUpdatedDateTime(), $right->lastUpdatedDateTime());
                }

                return $leftTimestamp <=> $rightTimestamp;
            }
        );

        $scanStartIndex = $this->determineScanStartIndex($trophyTitles, $gameLastUpdatedDate);
        $totalGamesToProcess = max(0, count($trophyTitles) - $scanStartIndex);
        $currentScanPosition = 0;
        $scannedGames = [];
        $restartScan = false;

        foreach ($trophyTitles as $index => $trophyTitle) {
            $npid = $trophyTitle->npCommunicationId();
            $scannedGames[] = $npid;

            if ($index < $scanStartIndex) {
                continue;
            }

            $trophyTitle = $this->trophyTitleRefresher->ensureTrophyTitleIcon(
                $user,
                $trophyTitle,
                (string) $player['online_id']
            );

            if ($trophyTitle === null) {
                $this->logger->log(sprintf(
                    'Unable to fetch trophy title icon for %s. Restarting scan.',
                    (string) $player['online_id']
                ));
                $restartScan = true;

                break;
            }

            $trophyTitle = $this->trophyTitleRefresher->ensureValidTrophyTitleLastUpdatedDate(
                $user,
                $trophyTitle,
                (string) $player['online_id']
            );

            if ($trophyTitle === null) {
                if ($this->titleMetadataHelper->shouldRetryInvalidTitleLastUpdatedDate($invalidTitleDateRetry, $onlineId, $npid)) {
                    $this->titleMetadataHelper->markInvalidTitleLastUpdatedDateRetried($invalidTitleDateRetry, $onlineId, $npid);

                    $this->logger->log(sprintf(
                        'Unable to fetch a valid last updated date for %s on title %s. Waiting 1 minute before retrying.',
                        (string) $player['online_id'],
                        $npid
                    ));
                    $this->trophyTitleRefresher->pauseBeforeRetryingInvalidApiResponse((int) $worker['id'], $onlineId);
                    $restartScan = true;

                    break;
                }

                $this->handleInvalidTitleLastUpdatedDateResponse(
                    $player,
                    (int) $worker['id'],
                    $npid
                );

                return PlayerScanTrophyTitleLoopResult::continueLoop();
            }

            if ($totalGamesToProcess > 0) {
                $currentScanPosition++;
                $this->workerScanCoordinator->setWorkerScanProgress(
                    (int) $worker['id'],
                    [
                        'current' => $currentScanPosition,
                        'total' => $totalGamesToProcess,
                        'title' => $trophyTitle->name(),
                        'npCommunicationId' => $npid,
                    ]
                );
            }

            if (
                isset($gameLastUpdatedDate[$npid])
                && $this->titleMetadataHelper->gameTimestampsMatch(
                    $trophyTitle->lastUpdatedDateTime(),
                    $gameLastUpdatedDate[$npid]
                )
            ) {
                continue;
            }

            $catalogSyncResult = $this->titleCatalogSynchronizer->synchronizeCatalog($trophyTitle, $client);
            if ($catalogSyncResult->restartScan) {
                $restartScan = true;

                break;
            }

            $this->trophyProgressSynchronizer->synchronizeTrophyProgress(
                $user,
                $trophyTitle,
                $npid,
                $catalogSyncResult->newTrophies,
                $catalogSyncResult->mergeParentsToRecompute,
            );
        }

        if ($restartScan) {
            $recheck = '';

            return PlayerScanTrophyTitleLoopResult::continueLoop();
        }

        $totalTrophiesEnd = $user->trophySummary()->platinum()
            + $user->trophySummary()->gold()
            + $user->trophySummary()->silver()
            + $user->trophySummary()->bronze();

        if ($totalTrophiesStart !== $totalTrophiesEnd) {
            $recheck = '';

            return PlayerScanTrophyTitleLoopResult::continueLoop();
        }

        $ourGameCount = $this->staleGameDeletionService->countLocalGames((string) $user->accountId());

        $scanReachedEnd = $currentScanPosition === $totalGamesToProcess;
        $scanCompletedCleanly = $trophyTitleFetchCompleted
            && $scanReachedEnd
            && !$restartScan
            && $recheck === '';

        $shouldDeleteMissingGames = $this->staleGameDeletionService->shouldDeleteMissingZeroPercentGames(
            (int) $psnGameCount,
            $ourGameCount,
            $scannedGames
        );

        $gameCountDelta = $psnGameCount - $ourGameCount;

        if ($this->staleGameDeletionService->shouldSuppressDeletionForIncompleteScan(
            $shouldDeleteMissingGames,
            $gameCountDelta,
            $scanCompletedCleanly,
        )) {
            $shouldDeleteMissingGames = false;
        }

        if ($shouldDeleteMissingGames) {
            if (!($missingGameDeletionCheck[$onlineId] ?? false)) {
                $missingGameDeletionCheck[$onlineId] = true;
                $this->workerScanCoordinator->setWaitingScanProgress(
                    (int) $worker['id'],
                    'Waiting 5 minutes before retrying because of game deletion check.'
                );
                ($this->sleeper)(60 * 5);
                $recheck = '';

                return PlayerScanTrophyTitleLoopResult::continueLoop();
            }

            $this->staleGameDeletionService->deleteMissingZeroPercentGames(
                (string) $user->accountId(),
                $scannedGames,
            );
        } elseif ($this->staleGameDeletionService->shouldRetryWhenSonyReturnsNoGames((int) $psnGameCount, $ourGameCount)) {
            if (!($missingTrophyTitleRetry[$onlineId] ?? false)) {
                $missingTrophyTitleRetry[$onlineId] = true;

                $this->workerScanCoordinator->setWaitingScanProgress(
                    (int) $worker['id'],
                    'No trophy titles returned. Waiting 1 minute before retrying.'
                );

                ($this->sleeper)(60 * 1);
                $recheck = '';

                return PlayerScanTrophyTitleLoopResult::continueLoop();
            }

            $this->logger->log(sprintf(
                'Skipped deleting missing games for %s (%s) because no trophy titles were returned.',
                (string) $player['online_id'],
                (string) $user->accountId()
            ));
        }

        $completionResult = $this->scanCompletionService->recalculatePlayerTrophyStatsAndStatus(
            (string) $user->accountId(),
            $totalTrophiesStart,
            $recheck,
        );

        if ($completionResult->shouldContinueScan()) {
            return PlayerScanTrophyTitleLoopResult::continueLoop();
        }

        return PlayerScanTrophyTitleLoopResult::proceedToFinalize();
    }

    /**
     * @param array<int, object> $trophyTitles
     * @param array<string, string> $gameLastUpdatedDate
     */
    public function determineScanStartIndex(array $trophyTitles, array $gameLastUpdatedDate): int
    {
        foreach ($trophyTitles as $index => $trophyTitle) {
            $npid = $trophyTitle->npCommunicationId();

            if (!isset($gameLastUpdatedDate[$npid])) {
                return (int) $index;
            }

            if (!$this->titleMetadataHelper->gameTimestampsMatch($trophyTitle->lastUpdatedDateTime(), $gameLastUpdatedDate[$npid])) {
                return (int) $index;
            }
        }

        return count($trophyTitles);
    }

    /**
     * @param array<string, mixed> $player
     */
    private function handleInvalidTitleLastUpdatedDateResponse(
        array $player,
        int $workerId,
        string $npCommunicationId
    ): void {
        $this->logger->log(
            sprintf(
                'Failed to scan %s because trophy title %s still has an invalid last updated date after retrying.',
                (string) ($player['online_id'] ?? ''),
                $npCommunicationId
            )
        );

        $this->workerScanCoordinator->deferPlayerScanAfterFailure($player, $workerId);
    }
}
