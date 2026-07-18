<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';
require_once __DIR__ . '/../Admin/PlayStationWorkerAuthenticator.php';
require_once __DIR__ . '/../Admin/WorkerService.php';
require_once __DIR__ . '/../AutomaticTrophyTitleMergeService.php';
require_once __DIR__ . '/../ImageHashCalculator.php';
require_once __DIR__ . '/../Psn100Logger.php';
require_once __DIR__ . '/../TrophyHistoryRecorder.php';
require_once __DIR__ . '/../TrophyMergeService.php';
require_once __DIR__ . '/CronWorkerLoginSession.php';
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
require_once __DIR__ . '/PlayerScanCatalogSideEffects.php';
require_once __DIR__ . '/PlayerScanTitleCatalogSyncResult.php';
require_once __DIR__ . '/PlayerScanTrophyProgressSynchronizer.php';
require_once __DIR__ . '/PlayerScanPrivacyService.php';
require_once __DIR__ . '/PlayerScanTrophySummaryAccessResult.php';
require_once __DIR__ . '/PlayerScanTrophyTitleRefresher.php';
require_once __DIR__ . '/PlayerScanTrophyTitleLoop.php';
require_once __DIR__ . '/PlayerScanTrophyTitleLoopResult.php';

use Tustin\Haste\Exception\NotFoundHttpException;
use Tustin\Haste\Exception\UnauthorizedHttpException;

final readonly class ThirtyMinuteCronJob implements CronJobInterface
{
    private readonly AutomaticTrophyTitleMergeService $automaticTrophyTitleMergeService;

    private readonly ImageHashCalculator $imageHashCalculator;
    private readonly WorkerScanCoordinator $workerScanCoordinator;
    private readonly CronWorkerLoginSession $workerLoginSession;
    private readonly PlayerScanQueueSelector $playerScanQueueSelector;
    private readonly PlayStationWorkerAuthenticator $workerAuthenticator;
    private readonly PlayerScanTitleMetadataHelper $titleMetadataHelper;
    private readonly PlayerScanProfileSynchronizer $profileSynchronizer;
    private readonly PlayerScanCompletionService $scanCompletionService;
    private readonly PlayerEarnedTrophyPersister $earnedTrophyPersister;
    private readonly PlayerScanStaleGameDeletionService $staleGameDeletionService;
    private readonly PlayerScanTitleCatalogSynchronizer $titleCatalogSynchronizer;
    private readonly PlayerScanTrophyProgressSynchronizer $trophyProgressSynchronizer;
    private readonly PlayerScanPrivacyService $privacyService;
    private readonly PlayerScanTrophyTitleRefresher $trophyTitleRefresher;
    private readonly PlayerScanTrophyTitleLoop $trophyTitleLoop;

    public function __construct(
        private readonly PDO $database,
        private readonly TrophyCalculator $trophyCalculator,
        private readonly Psn100Logger $logger,
        private readonly TrophyHistoryRecorder $historyRecorder,
        private readonly int $workerId,
        ?AutomaticTrophyTitleMergeService $automaticTrophyTitleMergeService = null,
        ?ImageHashCalculator $imageHashCalculator = null,
        ?WorkerScanCoordinator $workerScanCoordinator = null,
        ?CronWorkerLoginSession $workerLoginSession = null,
        ?PlayerScanQueueSelector $playerScanQueueSelector = null,
        ?PlayStationWorkerAuthenticator $workerAuthenticator = null,
        ?PlayerScanTitleMetadataHelper $titleMetadataHelper = null,
        ?PlayerScanProfileSynchronizer $profileSynchronizer = null,
        ?PlayerScanCompletionService $scanCompletionService = null,
        ?PlayerEarnedTrophyPersister $earnedTrophyPersister = null,
        ?PlayerScanStaleGameDeletionService $staleGameDeletionService = null,
        ?PlayerScanTitleCatalogSynchronizer $titleCatalogSynchronizer = null,
        ?PlayerScanTrophyProgressSynchronizer $trophyProgressSynchronizer = null,
        ?PlayerScanPrivacyService $privacyService = null,
        ?PlayerScanTrophyTitleRefresher $trophyTitleRefresher = null,
        ?PlayerScanTrophyTitleLoop $trophyTitleLoop = null,
    )
    {
        $this->automaticTrophyTitleMergeService = $automaticTrophyTitleMergeService
            ?? new AutomaticTrophyTitleMergeService($database, new TrophyMergeService($database));
        $this->imageHashCalculator = $imageHashCalculator ?? new ImageHashCalculator();
        $this->workerScanCoordinator = $workerScanCoordinator ?? new WorkerScanCoordinator($database);
        $this->workerAuthenticator = $workerAuthenticator ?? PlayStationWorkerAuthenticator::fromWorkerService(
            new WorkerService($database),
            null,
            function (int $workerId, string $message) use ($logger): void {
                $logger->log(sprintf('Failed to persist refresh token for worker %d: %s', $workerId, $message));
            },
        );
        $this->workerLoginSession = $workerLoginSession ?? new CronWorkerLoginSession(
            $database,
            $this->workerAuthenticator,
            $this->workerScanCoordinator,
            $logger,
        );
        $this->playerScanQueueSelector = $playerScanQueueSelector ?? new PlayerScanQueueSelector($database);
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
            catalogSideEffects: new PlayerScanCatalogSideEffects(
                $database,
                $historyRecorder,
                $this->automaticTrophyTitleMergeService,
            ),
        );
        $this->trophyProgressSynchronizer = $trophyProgressSynchronizer ?? new PlayerScanTrophyProgressSynchronizer(
            $database,
            $trophyCalculator,
            $logger,
            $this->earnedTrophyPersister,
            $this->automaticTrophyTitleMergeService,
        );
        $this->privacyService = $privacyService ?? new PlayerScanPrivacyService(
            $database,
            $this->workerScanCoordinator,
        );
        $this->trophyTitleRefresher = $trophyTitleRefresher ?? new PlayerScanTrophyTitleRefresher(
            $logger,
            $this->titleMetadataHelper,
            $this->workerScanCoordinator,
        );
        $this->trophyTitleLoop = $trophyTitleLoop ?? new PlayerScanTrophyTitleLoop(
            $database,
            $logger,
            $this->workerScanCoordinator,
            $this->titleMetadataHelper,
            $this->titleCatalogSynchronizer,
            $this->trophyProgressSynchronizer,
            $this->staleGameDeletionService,
            $this->scanCompletionService,
            $this->trophyTitleRefresher,
        );
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
            $loginResult = $this->workerLoginSession->authenticate($this->workerId);
            $client = $loginResult['client'];
            $worker = $loginResult['worker'];

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

            $trophySummaryAccess = $this->privacyService->resolveTrophySummaryLevel($user, (int) $worker['id']);

            if ($trophySummaryAccess->shouldAbortScan()) {
                break;
            }

            $privateUser = $trophySummaryAccess->isPrivateProfile();
            $level = $trophySummaryAccess->isAccessible() ? $trophySummaryAccess->level : 0;

            try {
                if (!$privateUser) {
                    if ($level !== 0) {
                        $loopResult = $this->trophyTitleLoop->processAccessibleTrophyTitles(
                            $client,
                            $user,
                            $player,
                            $worker,
                            $onlineId,
                            $recheck,
                            $missingGameDeletionCheck,
                            $missingTrophyTitleRetry,
                            $trophyTitleCountRetry,
                            $invalidTitleDateRetry,
                        );

                        if ($loopResult->shouldContinueLoop()) {
                            continue;
                        }
                    }

                    $this->scanCompletionService->updateRarityPointsForActivePlayer((string) $user->accountId());

                    $this->scanCompletionService->finalizeSuccessfulScan(
                        (string) $user->accountId(),
                        $user->onlineId(),
                    );

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
            } catch (TypeError | Exception $exception) {
                // Transient PSN/network failures (e.g. Guzzle/cURL transfer errors) should
                // back off and keep the worker alive instead of terminating the cron job.
                // Guzzle's httpErrors middleware can also TypeError when a null response
                // reaches a ResponseInterface-typed onFulfilled handler after a network failure.
                // Lock wait timeouts usually clear quickly, so retry sooner than other errors.
                $isLockWaitTimeout = $this->isLockWaitTimeoutException($exception);
                $waitSeconds = $isLockWaitTimeout ? 5 : 60;
                $waitDescription = $isLockWaitTimeout ? '5 seconds' : '1 minute';

                $this->logger->log(sprintf(
                    'Encountered a problem while scanning %s: %s. Waiting %s before retrying.',
                    $onlineId,
                    $exception->getMessage(),
                    $waitDescription
                ));
                $this->workerScanCoordinator->setWaitingScanProgress(
                    (int) $worker['id'],
                    sprintf(
                        'Encountered a problem while scanning. Waiting %s before retrying.',
                        $waitDescription
                    )
                );
                sleep($waitSeconds);
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

    private function isLockWaitTimeoutException(Throwable $exception): bool
    {
        if ($exception instanceof PDOException && (($exception->errorInfo[1] ?? null) === 1205)) {
            return true;
        }

        return str_contains($exception->getMessage(), 'Lock wait timeout exceeded');
    }
}