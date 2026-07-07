<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';
require_once __DIR__ . '/../Admin/PlayStationWorkerAuthenticator.php';
require_once __DIR__ . '/../Admin/Worker.php';
require_once __DIR__ . '/../Admin/WorkerService.php';
require_once __DIR__ . '/../AutomaticTrophyTitleMergeService.php';
require_once __DIR__ . '/../ImageHashCalculator.php';
require_once __DIR__ . '/../Psn100Logger.php';
require_once __DIR__ . '/../TrophyHistoryRecorder.php';
require_once __DIR__ . '/../TrophyMergeService.php';
require_once __DIR__ . '/WorkerScanCoordinator.php';
require_once __DIR__ . '/PlayerScanQueueSelector.php';
require_once __DIR__ . '/PlayerScanTitleMetadataHelper.php';
require_once __DIR__ . '/PlayerScanProfileSyncResult.php';
require_once __DIR__ . '/PlayerAvatarSynchronizer.php';
require_once __DIR__ . '/PlayerScanProfileSynchronizer.php';
require_once __DIR__ . '/PlayerScanCompletionResult.php';
require_once __DIR__ . '/PlayerScanCompletionService.php';
require_once __DIR__ . '/PlayerEarnedTrophyPersister.php';
require_once __DIR__ . '/PlayerScanStaleGameDeletionService.php';
require_once __DIR__ . '/PlayerScanTitleCatalogSynchronizer.php';
require_once __DIR__ . '/PlayerScanTitleCatalogSyncResult.php';

use Tustin\Haste\Exception\NotFoundHttpException;
use Tustin\Haste\Exception\UnauthorizedHttpException;
use Tustin\PlayStation\Client;

final class ThirtyMinuteCronJob implements CronJobInterface
{
    private readonly AutomaticTrophyTitleMergeService $automaticTrophyTitleMergeService;

    private readonly ImageHashCalculator $imageHashCalculator;
    private readonly WorkerScanCoordinator $workerScanCoordinator;
    private readonly PlayerScanQueueSelector $playerScanQueueSelector;
    private readonly PlayStationWorkerAuthenticator $workerAuthenticator;
    private readonly PlayerScanTitleMetadataHelper $titleMetadataHelper;
    private readonly PlayerScanProfileSynchronizer $profileSynchronizer;
    private readonly PlayerScanCompletionService $scanCompletionService;
    private readonly PlayerEarnedTrophyPersister $earnedTrophyPersister;
    private readonly PlayerScanStaleGameDeletionService $staleGameDeletionService;
    private readonly PlayerScanTitleCatalogSynchronizer $titleCatalogSynchronizer;

    public function __construct(
        private readonly PDO $database,
        private readonly TrophyCalculator $trophyCalculator,
        private readonly Psn100Logger $logger,
        private readonly TrophyHistoryRecorder $historyRecorder,
        private readonly int $workerId,
        ?AutomaticTrophyTitleMergeService $automaticTrophyTitleMergeService = null,
        ?ImageHashCalculator $imageHashCalculator = null,
        ?WorkerScanCoordinator $workerScanCoordinator = null,
        ?PlayerScanQueueSelector $playerScanQueueSelector = null,
        ?PlayStationWorkerAuthenticator $workerAuthenticator = null,
        ?PlayerScanTitleMetadataHelper $titleMetadataHelper = null,
        ?PlayerScanProfileSynchronizer $profileSynchronizer = null,
        ?PlayerScanCompletionService $scanCompletionService = null,
        ?PlayerEarnedTrophyPersister $earnedTrophyPersister = null,
        ?PlayerScanStaleGameDeletionService $staleGameDeletionService = null,
        ?PlayerScanTitleCatalogSynchronizer $titleCatalogSynchronizer = null,
    )
    {
        $this->automaticTrophyTitleMergeService = $automaticTrophyTitleMergeService
            ?? new AutomaticTrophyTitleMergeService($database, new TrophyMergeService($database));
        $this->imageHashCalculator = $imageHashCalculator ?? new ImageHashCalculator();
        $this->workerScanCoordinator = $workerScanCoordinator ?? new WorkerScanCoordinator($database);
        $this->playerScanQueueSelector = $playerScanQueueSelector ?? new PlayerScanQueueSelector($database);
        $this->workerAuthenticator = $workerAuthenticator ?? PlayStationWorkerAuthenticator::fromWorkerService(
            new WorkerService($database),
            null,
            function (int $workerId, string $message) use ($logger): void {
                $logger->log(sprintf('Failed to persist refresh token for worker %d: %s', $workerId, $message));
            },
        );
        $this->titleMetadataHelper = $titleMetadataHelper ?? new PlayerScanTitleMetadataHelper();
        $this->profileSynchronizer = $profileSynchronizer ?? new PlayerScanProfileSynchronizer(
            $database,
            $logger,
            $this->workerScanCoordinator,
            new PlayerAvatarSynchronizer($database, $this->imageHashCalculator),
        );
        $this->scanCompletionService = $scanCompletionService ?? new PlayerScanCompletionService($database);
        $this->earnedTrophyPersister = $earnedTrophyPersister ?? new PlayerEarnedTrophyPersister(
            $database,
            $this->titleMetadataHelper,
        );
        $this->staleGameDeletionService = $staleGameDeletionService ?? new PlayerScanStaleGameDeletionService($database);
        $this->titleCatalogSynchronizer = $titleCatalogSynchronizer ?? new PlayerScanTitleCatalogSynchronizer(
            $database,
            $logger,
            historyRecorder: $historyRecorder,
            automaticTrophyTitleMergeService: $this->automaticTrophyTitleMergeService,
        );
    }

    /**
     * @param array<int, object> $trophyTitles
     * @param array<string, string> $gameLastUpdatedDate
     */
    private function determineScanStartIndex(array $trophyTitles, array $gameLastUpdatedDate): int
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
    private function handleInvalidApiResponse(array $player, int $workerId, Throwable $exception): void
    {
        // Middleware/type mismatches from the PSN client are treated as temporary API failures, not permanent "player not found" states.
        $this->logger->log(
            sprintf(
                'Failed to scan %s because the PlayStation API returned an invalid response: %s',
                (string) ($player['online_id'] ?? ''),
                $exception->getMessage()
            )
        );

        $this->workerScanCoordinator->deferPlayerScanAfterFailure($player, $workerId);
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

    private function pauseBeforeRetryingInvalidApiResponse(int $workerId, string $onlineId): void
    {
        $this->workerScanCoordinator->setWaitingScanProgress(
            $workerId,
            sprintf(
                'Encountered an invalid response from the PlayStation API while scanning %s. Waiting 1 minute before retrying.',
                $onlineId
            )
        );

        sleep(60);
    }

    /**
     * @template T
     * @param callable():T $operation
     * @return T
     */
    private function retryNotFound(callable $operation, string $description)
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

    private function ensureTrophyTitleIcon(
        object $user,
        object $trophyTitle,
        string $onlineId
    ): ?object {
        $maxAttempts = 2;
        $npCommunicationId = (string) $trophyTitle->npCommunicationId();

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $iconUrl = trim((string) $trophyTitle->iconUrl());

            if ($iconUrl !== '') {
                return $trophyTitle;
            }

            if ($attempt === $maxAttempts) {
                $titleName = trim((string) $trophyTitle->name());
                $titleNameForLog = $titleName === '' ? $npCommunicationId : $titleName;

                $this->logger->log(sprintf(
                    'Trophy title "%s" (%s) is missing an icon while processing user %s (attempt %d/%d).',
                    $titleNameForLog,
                    $npCommunicationId,
                    $onlineId,
                    $attempt,
                    $maxAttempts
                ));

                break;
            }

            sleep(2);

            $trophyTitleCollection = $user->trophyTitles();

            foreach ($trophyTitleCollection as $refreshedTitle) {
                if ((string) $refreshedTitle->npCommunicationId() === $npCommunicationId) {
                    $trophyTitle = $refreshedTitle;
                    break;
                }
            }
        }

        return null;
    }

    private function ensureValidTrophyTitleLastUpdatedDate(
        object $user,
        object $trophyTitle,
        string $onlineId
    ): ?object {
        $maxAttempts = 2;
        $npCommunicationId = (string) $trophyTitle->npCommunicationId();

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($this->titleMetadataHelper->isValidSonyLastUpdatedDateTime($trophyTitle->lastUpdatedDateTime())) {
                return $trophyTitle;
            }

            if ($attempt === $maxAttempts) {
                $titleName = trim((string) $trophyTitle->name());
                $titleNameForLog = $titleName === '' ? $npCommunicationId : $titleName;

                $this->logger->log(sprintf(
                    'Trophy title "%s" (%s) has an invalid last updated date while processing user %s (attempt %d/%d).',
                    $titleNameForLog,
                    $npCommunicationId,
                    $onlineId,
                    $attempt,
                    $maxAttempts
                ));

                break;
            }

            sleep(2);

            $trophyTitleCollection = $user->trophyTitles();

            foreach ($trophyTitleCollection as $refreshedTitle) {
                if ((string) $refreshedTitle->npCommunicationId() === $npCommunicationId) {
                    $trophyTitle = $refreshedTitle;
                    break;
                }
            }
        }

        return null;
    }

    #[\Override]
    public function run(): void
    {
        $recheck = "";
        $missingGameDeletionCheck = [];
        $missingTrophyTitleRetry = [];
        $trophyTitleCountRetry = [];
        $invalidTitleDateRetry = [];

        while (true) {
            // Login with a token
            $loggedIn = false;
            while (!$loggedIn) {
                $query = $this->database->prepare("SELECT
                    id,
                    refresh_token,
                    npsso,
                    scanning
                FROM
                    setting
                WHERE
                    id = :id");
                $query->bindValue(":id", $this->workerId, PDO::PARAM_INT);
                $query->execute();
                $worker = $query->fetch(PDO::FETCH_ASSOC);

                if ($worker === false) {
                    $message = sprintf(
                        'Worker %d not found in setting table',
                        $this->workerId
                    );
                    $this->logger->log($message);

                    throw new RuntimeException($message);
                }

                try {
                    $workerAccount = new Worker(
                        (int) $worker['id'],
                        (string) ($worker['refresh_token'] ?? ''),
                        (string) ($worker['npsso'] ?? ''),
                        '',
                        new DateTimeImmutable('1970-01-01 00:00:00'),
                        null,
                    );
                    $client = $this->workerAuthenticator->authenticateWorker($workerAccount);
                    $loggedIn = true;
                } catch (TypeError $e) {
                    // Something odd, let's wait a minute
                    $this->workerScanCoordinator->setWaitingScanProgress(
                        (int) $worker['id'],
                        'Encountered a login problem. Waiting 1 minute before retrying.'
                    );
                    sleep(60 * 1);
                } catch (Exception $e) {
                    $this->logger->log("Can't login with worker ". $worker["id"]);

                    // Something went wrong, 'release' the current scanning profile so other workers can pick it up.
                    $this->workerScanCoordinator->releaseWorkerFromCurrentScan((int) $worker['id']);

                    // Wait 30 minutes to not hammer login
                    sleep(60 * 30);
                }
            }

            try {
                $player = $this->playerScanQueueSelector->selectNextCandidate((int) $worker['id']);
                $player = $this->workerScanCoordinator->reservePlayerForScanning((int) $worker['id'], $player);
            } catch (Exception $e) {
                // Probably just an exception for "Integrity constraint violation: 1062 Duplicate entry 'online_id' for key 'setting.scanning'" because another thread was faster then this one
                // Continue and try again!
                continue;
            }

            if ($player === null) {
                continue;
            }

            if ($recheck == $player["online_id"]) {
                $recheck = "";
            } else {
                $recheck = $player["online_id"];
            }

            $onlineId = (string) $player['online_id'];

            $profileSyncResult = $this->profileSynchronizer->synchronizeProfile(
                $client,
                $player,
                (int) $worker['id'],
                $onlineId,
            );

            if ($profileSyncResult->shouldSkipPlayer()) {
                continue;
            }

            $player = $profileSyncResult->player;
            $user = $profileSyncResult->user;
            $country = $profileSyncResult->country ?? 'zz';

            if ($user === null) {
                continue;
            }

            try {
                $level = 0;
                $level = $user->trophySummary();
            } catch (Exception $e) {
                // Wait 5 minutes to not hammer Sony
                $this->workerScanCoordinator->setWaitingScanProgress(
                    (int) $worker['id'],
                    'Encountered a problem while scanning. Waiting 1 minute before retrying.'
                );
                sleep(60 * 1);

                // Something is odd with PSN, break out and try again later.
                break;
            }

            $privateUser = false;
            try {
                $level = 0;
                $level = $user->trophySummary()->level();
            } catch (TypeError $e) {
                // Rare error, wait 1 minute to not hammer Sony and try again.
                $this->workerScanCoordinator->setWaitingScanProgress(
                    (int) $worker['id'],
                    'Encountered a problem while scanning. Waiting 1 minute before retrying.'
                );
                sleep(60 * 1);
                break;
            } catch (Exception $e) {
                // Potentially private profile, wait 1 minute and retry before updating the status.
                $this->workerScanCoordinator->setWaitingScanProgress(
                    (int) $worker['id'],
                    'Profile scan failed, waiting 1 minute before confirming privacy.'
                );
                sleep(60 * 1);

                try {
                    $level = 0;
                    $level = $user->trophySummary()->level();
                } catch (TypeError $retryException) {
                    // Rare error, wait 1 minute to not hammer Sony and try again.
                    $this->workerScanCoordinator->setWaitingScanProgress(
                        (int) $worker['id'],
                        'Encountered a problem while scanning. Waiting 1 minute before retrying.'
                    );
                    sleep(60 * 1);
                    break;
                } catch (Exception $retryException) {
                    // Profile seem to be private, set status to 3 to hide all trophies.
                    $query = $this->database->prepare("UPDATE
                            player
                        SET
                            status = 3,
                            last_updated_date = NOW()
                        WHERE
                            account_id = :account_id
                            AND status != 1
                    ");
                    $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                    $query->execute();

                    // Delete user from the queue
                    $query = $this->database->prepare("DELETE FROM player_queue
                        WHERE  online_id = :online_id ");
                    $query->bindValue(":online_id", $user->onlineId(), PDO::PARAM_STR);
                    $query->execute();

                    $privateUser = true;
                }
            }

            try {
                if (!$privateUser) {
                    $totalTrophiesStart = $user->trophySummary()->platinum() + $user->trophySummary()->gold() + $user->trophySummary()->silver() + $user->trophySummary()->bronze();

                    if ($level !== 0) {
                        $query = $this->database->prepare("SELECT np_communication_id,
                                last_updated_date
                            FROM   trophy_title_player
                            WHERE  account_id = :account_id AND np_communication_id LIKE 'N%'");
                        $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
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
                        } catch (TypeError $exception) {
                            // Unable to fetch trophy titles for player['online_id'] due to unexpected response.
                            sleep(5);

                            continue;
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

                                sleep(60 * 1);
                                $recheck = '';

                                continue;
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
                        $scannedGames = array();
                        $restartScan = false;

                        // Look through each and every game
                        foreach ($trophyTitles as $index => $trophyTitle) {
                            $npid = $trophyTitle->npCommunicationId();
                            array_push($scannedGames, $npid);

                            if ($index < $scanStartIndex) {
                                continue;
                            }

                            $trophyTitle = $this->ensureTrophyTitleIcon(
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

                            $trophyTitle = $this->ensureValidTrophyTitleLastUpdatedDate(
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
                                    $this->pauseBeforeRetryingInvalidApiResponse((int) $worker['id'], $onlineId);
                                    $restartScan = true;

                                    break;
                                }

                                $this->handleInvalidTitleLastUpdatedDateResponse(
                                    $player,
                                    (int) $worker['id'],
                                    $npid
                                );

                                continue 2;
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

                            // Does this user already have the game?
                            if (
                                isset($gameLastUpdatedDate[$npid])
                                && $this->titleMetadataHelper->gameTimestampsMatch(
                                    $trophyTitle->lastUpdatedDateTime(),
                                    $gameLastUpdatedDate[$npid]
                                )
                            ) {
                                // Game seems scanned already, skip to next.
                                continue;
                            }

                            $catalogSyncResult = $this->titleCatalogSynchronizer->synchronizeCatalog($trophyTitle, $client);
                            if ($catalogSyncResult->restartScan) {
                                $restartScan = true;

                                break;
                            }

                            $newTrophies = $catalogSyncResult->newTrophies;
                            $mergeParentsToRecompute = $catalogSyncResult->mergeParentsToRecompute;

                            // Fetch user trophies
                            $trophyGroups = $this->retryNotFound(
                                fn () => $trophyTitle->trophyGroups(),
                                sprintf('Fetching trophy groups for %s (%s)', $trophyTitle->name(), $npid)
                            );

                            foreach ($trophyGroups as $trophyGroup) {
                                $groupTrophies = $this->retryNotFound(
                                    fn () => $trophyGroup->trophies(),
                                    sprintf(
                                        'Fetching trophies for %s (%s/%s)',
                                        $trophyTitle->name(),
                                        $npid,
                                        $trophyGroup->id()
                                    )
                                );

                                foreach ($groupTrophies as $trophy) {
                                    $trophyEarned = $trophy->earned();
                                    $progress = (clone $trophy)->progress();
                                    if ($trophyEarned || ($progress != '' && intval($progress) > 0)) {
                                        $this->earnedTrophyPersister->persistEarnedTrophy(
                                            $npid,
                                            $trophyGroup->id(),
                                            (int) $trophy->id(),
                                            (string) $user->accountId(),
                                            $trophyEarned,
                                            $progress,
                                            $trophy->earnedDateTime(),
                                        );
                                    }
                                }

                                // Recalculate trophies for trophy group and player
                                $this->trophyCalculator->recalculateTrophyGroup($npid, $trophyGroup->id(), (int) $user->accountId());
                            }

                            // Recalculate trophies for trophy title and player
                            $this->trophyCalculator->recalculateTrophyTitle($npid, $trophyTitle->lastUpdatedDateTime(), $newTrophies, (int) $user->accountId(), false);

                            // Game Merge stuff
                            $query = $this->database->prepare("SELECT DISTINCT parent_np_communication_id, 
                                                parent_group_id 
                                FROM   trophy_merge 
                                WHERE  child_np_communication_id = :child_np_communication_id ");
                            $query->bindValue(":child_np_communication_id", $npid, PDO::PARAM_STR);
                            $query->execute();
                            while ($row = $query->fetch()) {
                                $this->trophyCalculator->recalculateTrophyGroup($row["parent_np_communication_id"], $row["parent_group_id"], (int) $user->accountId());
                                $this->trophyCalculator->recalculateTrophyTitle($row["parent_np_communication_id"], $trophyTitle->lastUpdatedDateTime(), false, (int) $user->accountId(), true);
                            }

                            foreach ($mergeParentsToRecompute as $mergeParent) {
                                $this->automaticTrophyTitleMergeService->recomputeMergeProgressByParent($mergeParent);
                            }
                        }

                        if ($restartScan) {
                            $recheck = '';

                            continue;
                        }

                        $totalTrophiesEnd = $user->trophySummary()->platinum() + $user->trophySummary()->gold() + $user->trophySummary()->silver() + $user->trophySummary()->bronze();
                        if ($totalTrophiesStart != $totalTrophiesEnd) { // New trophies during the scan, restart and get them as well.
                            $recheck = "";
                            continue;
                        }

                        // Delete missing 0% games (and this will also delete hidden games, and any trophies for those hidden games)
                        $ourGameCount = $this->staleGameDeletionService->countLocalGames((int) $user->accountId());

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
                            // $this->logger->log(sprintf(
                            //     'Skipping deletion for %s (%d) because the scan did not complete cleanly (psn=%d, local=%d).',
                            //     (string) $player['online_id'],
                            //     (int) $user->accountId(),
                            //     (int) $psnGameCount,
                            //     (int) $ourGameCount
                            // ));
                            $shouldDeleteMissingGames = false;
                        }

                        if ($shouldDeleteMissingGames) {
                            if (!($missingGameDeletionCheck[$onlineId] ?? false)) {
                                $missingGameDeletionCheck[$onlineId] = true;
                                $this->workerScanCoordinator->setWaitingScanProgress(
                                    (int) $worker['id'],
                                    'Waiting 5 minutes before retrying because of game deletion check.'
                                );
                                sleep(60 * 5);
                                $recheck = '';

                                continue;
                            }

                            $this->staleGameDeletionService->deleteMissingZeroPercentGames(
                                (int) $user->accountId(),
                                $scannedGames,
                            );
                        } elseif ($this->staleGameDeletionService->shouldRetryWhenSonyReturnsNoGames((int) $psnGameCount, $ourGameCount)) {
                            if (!($missingTrophyTitleRetry[$onlineId] ?? false)) {
                                $missingTrophyTitleRetry[$onlineId] = true;

                                $this->workerScanCoordinator->setWaitingScanProgress(
                                    (int) $worker['id'],
                                    'No trophy titles returned. Waiting 1 minute before retrying.'
                                );

                                sleep(60 * 1);
                                $recheck = '';

                                continue;
                            }

                            $this->logger->log(sprintf(
                                'Skipped deleting missing games for %s (%d) because no trophy titles were returned.',
                                (string) $player['online_id'],
                                (int) $user->accountId()
                            ));
                        }

                        $completionResult = $this->scanCompletionService->recalculatePlayerTrophyStatsAndStatus(
                            (int) $user->accountId(),
                            $totalTrophiesStart,
                            $recheck,
                        );

                        if ($completionResult->shouldContinueScan()) {
                            continue;
                        }
                    }

                    $this->scanCompletionService->updateRarityPointsForActivePlayer((int) $user->accountId());

                    // Done with the user, update the date
                    $query = $this->database->prepare("UPDATE player
                        SET    last_updated_date = Now()
                        WHERE  account_id = :account_id ");
                    $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                    $query->execute();

                    // Delete user from the queue
                    $query = $this->database->prepare("DELETE FROM player_queue
                        WHERE  online_id = :online_id ");
                    // Don't use $user->onlineId(), since the user can have changed its name from what was entered into the queue.
                    $query->bindValue(":online_id", $user->onlineId(), PDO::PARAM_STR);
                    $query->execute();

                    unset($missingGameDeletionCheck[$onlineId]);
                    unset($missingTrophyTitleRetry[$onlineId]);
                    unset($trophyTitleCountRetry[$onlineId]);
                    $this->titleMetadataHelper->clearInvalidTitleDateRetriesForPlayer($invalidTitleDateRetry, $onlineId);
                }
            } catch (NotFoundHttpException $exception) {
                sleep(2);
                $recheck = '';
                unset($missingGameDeletionCheck[$onlineId]);
                unset($missingTrophyTitleRetry[$onlineId]);
                unset($trophyTitleCountRetry[$onlineId]);
                $this->titleMetadataHelper->clearInvalidTitleDateRetriesForPlayer($invalidTitleDateRetry, $onlineId);

                continue;
            } catch (UnauthorizedHttpException $exception) {
                sleep(2);
                $recheck = '';
                unset($missingGameDeletionCheck[$onlineId]);
                unset($missingTrophyTitleRetry[$onlineId]);
                unset($trophyTitleCountRetry[$onlineId]);
                $this->titleMetadataHelper->clearInvalidTitleDateRetriesForPlayer($invalidTitleDateRetry, $onlineId);

                continue;
            } finally {
                $this->workerScanCoordinator->setWorkerScanProgress((int) $worker['id'], null);
            }
        }
    }
}