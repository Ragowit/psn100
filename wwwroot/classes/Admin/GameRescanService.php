<?php

declare(strict_types=1);

require_once __DIR__ . '/GameRescanProgressListener.php';
require_once __DIR__ . '/GameRescanProgressReporter.php';
require_once __DIR__ . '/GameRescanDifferenceTracker.php';
require_once __DIR__ . '/GameRescanResult.php';
require_once __DIR__ . '/GameRescanCatalogUpdater.php';
require_once __DIR__ . '/../ImageHashCalculator.php';
require_once __DIR__ . '/../TrophyHistoryRecorder.php';
require_once __DIR__ . '/../TrophyMetaRepository.php';
require_once __DIR__ . '/../TrophyImageDirectories.php';
require_once __DIR__ . '/../TrophyImageDownloader.php';
require_once __DIR__ . '/../TrophyCatalogSynchronizer.php';
require_once __DIR__ . '/../ChangelogEntry.php';
require_once __DIR__ . '/PsnGameLookupService.php';
require_once __DIR__ . '/PsnTrophyLookupGroupDataProvider.php';
require_once __DIR__ . '/GameRescanPsnAccessor.php';
require_once __DIR__ . '/PlayStationWorkerAuthenticator.php';
require_once __DIR__ . '/WorkerService.php';

use Tustin\PlayStation\Client;

class GameRescanService
{
    private readonly PDO $database;
    private readonly TrophyCalculator $trophyCalculator;

    private readonly TrophyHistoryRecorder $historyRecorder;

    private readonly ImageHashCalculator $imageHashCalculator;
    private readonly PsnGameLookupService $psnGameLookupService;
    private readonly PsnTrophyLookupGroupDataProvider $trophyLookupGroupDataProvider;
    private readonly TrophyImageDirectories $imageDirectories;
    private TrophyImageDownloader $imageDownloader;
    private readonly GameRescanPsnAccessor $psnAccessor;
    private readonly TrophyCatalogSynchronizer $trophyCatalogSynchronizer;
    private GameRescanCatalogUpdater $catalogUpdater;

    /**
     * @var \Closure(string):void|null
     */
    private ?\Closure $logListener = null;

    public function __construct(
        PDO $database,
        TrophyCalculator $trophyCalculator,
        ?TrophyHistoryRecorder $historyRecorder = null,
        ?ImageHashCalculator $imageHashCalculator = null,
        ?PsnGameLookupService $psnGameLookupService = null,
        ?PsnTrophyLookupGroupDataProvider $trophyLookupGroupDataProvider = null,
        ?TrophyImageDirectories $imageDirectories = null,
        ?TrophyImageDownloader $imageDownloader = null,
        ?PlayStationWorkerAuthenticator $workerAuthenticator = null,
        ?GameRescanPsnAccessor $psnAccessor = null,
        ?TrophyCatalogSynchronizer $trophyCatalogSynchronizer = null,
        ?GameRescanCatalogUpdater $catalogUpdater = null,
    )
    {
        $this->database = $database;
        $this->trophyCalculator = $trophyCalculator;
        $this->historyRecorder = $historyRecorder ?? new TrophyHistoryRecorder($database);
        $this->imageHashCalculator = $imageHashCalculator ?? new ImageHashCalculator();
        $this->psnGameLookupService = $psnGameLookupService ?? PsnGameLookupService::fromDatabase($database);
        $this->trophyLookupGroupDataProvider = $trophyLookupGroupDataProvider
            ?? new PsnTrophyLookupGroupDataProvider($this->psnGameLookupService);
        $this->imageDirectories = $imageDirectories ?? TrophyImageDirectories::productionDefault();
        $this->imageDownloader = $imageDownloader ?? new TrophyImageDownloader($this->imageHashCalculator);
        $this->trophyCatalogSynchronizer = $trophyCatalogSynchronizer ?? new TrophyCatalogSynchronizer($database);
        $workerAuthenticator = $workerAuthenticator ?? PlayStationWorkerAuthenticator::fromWorkerService(
            new WorkerService($database),
        );
        $this->psnAccessor = $psnAccessor ?? new GameRescanPsnAccessor($database, $workerAuthenticator);
        $this->catalogUpdater = $catalogUpdater ?? new GameRescanCatalogUpdater(
            $database,
            $this->trophyCatalogSynchronizer,
            new TrophyMetaRepository($database),
            $this->trophyLookupGroupDataProvider,
            $this->imageDirectories,
            $this->imageDownloader,
        );
    }

    /**
     * @param callable(string):void|null $logListener
     */
    public function rescan(
        int $gameId,
        ?GameRescanProgressListener $progressListener = null,
        ?callable $logListener = null
    ): GameRescanResult {
        $previousLogListener = $this->logListener;
        $this->logListener = $logListener !== null
            ? $logListener(...)
            : null;
        $previousImageDownloader = $this->imageDownloader;
        $this->imageDownloader = $this->imageDownloader->withLogger(
            function (string $message): void {
                $this->logMessage($message);
            }
        );
        $previousCatalogUpdater = $this->catalogUpdater;
        $this->catalogUpdater = new GameRescanCatalogUpdater(
            $this->database,
            $this->trophyCatalogSynchronizer,
            new TrophyMetaRepository($this->database),
            $this->trophyLookupGroupDataProvider,
            $this->imageDirectories,
            $this->imageDownloader,
        );

        try {
            $differenceTracker = new GameRescanDifferenceTracker();
            $progressReporter = new GameRescanProgressReporter($progressListener);
            $progressReporter->notify(5, 'Validating game id…');
            $npCommunicationId = $this->psnAccessor->getGameNpCommunicationId($gameId);

            $progressReporter->notify(10, 'Checking game entry…');

            if (!$this->psnAccessor->isOriginalGame($npCommunicationId)) {
                return new GameRescanResult(
                    'Can only rescan original game entries.',
                    $differenceTracker->getDifferences()
                );
            }

            $progressReporter->notify(15, 'Signing in to worker account…');
            $client = $this->psnAccessor->loginToWorker(
                function (int $workerId, Throwable $exception): void {
                    $this->logMessage("Can't login with worker " . $workerId);
                },
            );
            $progressReporter->notify(20, 'Locating accessible player…');
            $user = $this->psnAccessor->findAccessibleUserWithGame($client, $npCommunicationId);

            if ($user === null) {
                throw new RuntimeException('Unable to find accessible player for the specified game.');
            }

            $trophyTitle = $this->psnAccessor->findTrophyTitleForUser($user, $npCommunicationId);

            if ($trophyTitle === null) {
                throw new RuntimeException('Unable to find trophy title for the specified game.');
            }

            $progressReporter->notify(25, 'Refreshing trophy details…');
            $trophyGroups = $this->catalogUpdater->updateFromPsn(
                $client,
                $trophyTitle,
                $npCommunicationId,
                $progressReporter,
                $differenceTracker
            );
            $progressReporter->notify(70, 'Recalculating player statistics…');
            $this->recalculateTrophies(
                $trophyTitle,
                $npCommunicationId,
                (string) $user->accountId(),
                $trophyGroups,
                $progressReporter
            );

            $progressReporter->notify(85, 'Updating trophy set version…');
            $this->updateTrophySetVersion(
                $npCommunicationId,
                $trophyTitle->trophySetVersion(),
                $differenceTracker
            );
            $progressReporter->notify(90, 'Recording rescan details…');
            $this->recordRescan($gameId);

            if ($differenceTracker->getDifferences() !== []) {
                $this->historyRecorder->recordByTitleId($gameId);
            }

            $message = "Game {$gameId} have been rescanned.";

            return new GameRescanResult($message, $differenceTracker->getDifferences());
        } finally {
            $this->logListener = $previousLogListener;
            $this->imageDownloader = $previousImageDownloader;
            $this->catalogUpdater = $previousCatalogUpdater;
        }
    }

    private function logMessage(string $message): void
    {
        if ($this->logListener !== null) {
            ($this->logListener)($message);

            return;
        }

        $query = $this->database->prepare('INSERT INTO log(message) VALUES(:message)');
        $query->bindValue(':message', $message, PDO::PARAM_STR);
        $query->execute();
    }

    private function recalculateTrophies(
        object $trophyTitle,
        string $npCommunicationId,
        string $accountId,
        array $trophyGroups,
        GameRescanProgressReporter $progressReporter
    ): void {
        $baseMessage = 'Recalculating player statistics…';
        $totalGroups = count($trophyGroups);
        $currentGroup = 0;

        foreach ($trophyGroups as $trophyGroup) {
            $this->trophyCalculator->recalculateTrophyGroup($npCommunicationId, $trophyGroup->id(), $accountId);

            $currentGroup++;
            $progressReporter->notifyRange(
                70,
                82,
                $currentGroup,
                $totalGroups,
                sprintf('%s (%d/%d)', $baseMessage, $currentGroup, $totalGroups)
            );
        }

        $this->trophyCalculator->recalculateTrophyTitle(
            $npCommunicationId,
            $trophyTitle->lastUpdatedDateTime(),
            true,
            $accountId,
            false
        );

        $progressReporter->notify(83, $baseMessage);

        $this->recalculateParentTitles(
            $npCommunicationId,
            $trophyTitle->lastUpdatedDateTime(),
            $accountId,
            $progressReporter
        );

        $progressReporter->notify(84, $baseMessage);
    }

    private function recalculateParentTitles(
        string $childNpCommunicationId,
        string $lastUpdatedDateTime,
        string $accountId,
        GameRescanProgressReporter $progressReporter
    ): void {
        $query = $this->database->prepare(
            'SELECT DISTINCT parent_np_communication_id, parent_group_id
            FROM trophy_merge
            WHERE child_np_communication_id = :child_np_communication_id'
        );
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        $totalParents = count($rows);
        $currentParent = 0;

        foreach ($rows as $row) {
            $parentNpCommunicationId = (string) $row['parent_np_communication_id'];
            $parentGroupId = (string) $row['parent_group_id'];

            $this->trophyCalculator->recalculateTrophyGroup($parentNpCommunicationId, $parentGroupId, $accountId);
            $this->trophyCalculator->recalculateTrophyTitle(
                $parentNpCommunicationId,
                $lastUpdatedDateTime,
                false,
                $accountId,
                true
            );

            $currentParent++;
            $progressReporter->notifyRange(
                83,
                84,
                $currentParent,
                $totalParents,
                sprintf('Recalculating merged trophy titles… (%d/%d)', $currentParent, $totalParents)
            );
        }
    }

    private function updateTrophySetVersion(
        string $npCommunicationId,
        string $setVersion,
        GameRescanDifferenceTracker $differenceTracker
    ): void {
        $previousVersion = $this->fetchCurrentTrophySetVersion($npCommunicationId);

        if (!GameRescanCatalogUpdater::isSetVersionAtLeastCurrent($setVersion, $previousVersion)) {
            return;
        }

        $query = $this->database->prepare(
            'UPDATE trophy_title
            SET set_version = :set_version
            WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':set_version', $setVersion, PDO::PARAM_STR);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $differenceTracker->recordTitleChange('Set Version', $previousVersion, $setVersion);
    }

    private function recordRescan(int $gameId): void
    {
        $query = $this->database->prepare(
            'INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES (:change_type, :param_1)'
        );
        $query->bindValue(':change_type', ChangelogEntryType::GAME_RESCAN->value, PDO::PARAM_STR);
        $query->bindValue(':param_1', $gameId, PDO::PARAM_INT);
        $query->execute();
    }

    private function fetchCurrentTrophySetVersion(string $npCommunicationId): ?string
    {
        $query = $this->database->prepare(
            'SELECT set_version FROM trophy_title WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $version = $query->fetchColumn();

        if ($version === false) {
            return null;
        }

        $version = (string) $version;

        return $version === '' ? null : $version;
    }
}
