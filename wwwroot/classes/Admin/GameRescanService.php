<?php

declare(strict_types=1);

require_once __DIR__ . '/GameRescanProgressListener.php';
require_once __DIR__ . '/GameRescanProgressReporter.php';
require_once __DIR__ . '/GameRescanDifferenceTracker.php';
require_once __DIR__ . '/GameRescanResult.php';
require_once __DIR__ . '/../ImageHashCalculator.php';
require_once __DIR__ . '/../TrophyHistoryRecorder.php';
require_once __DIR__ . '/../TrophyMetaRepository.php';
require_once __DIR__ . '/../TrophyImageDirectories.php';
require_once __DIR__ . '/../TrophyImageDownloader.php';
require_once __DIR__ . '/../TrophyCatalogSynchronizer.php';
require_once __DIR__ . '/PsnGameLookupService.php';
require_once __DIR__ . '/PsnTrophyLookupGroupDataProvider.php';
require_once __DIR__ . '/PlayStationWorkerAuthenticator.php';
require_once __DIR__ . '/WorkerService.php';

use Tustin\PlayStation\Client;

class GameRescanService
{
    private const ORIGINAL_GAME_PREFIX = 'NPWR';
    private const LOGIN_RETRY_DELAY_SECONDS = 300;

    private PDO $database;
    private TrophyCalculator $trophyCalculator;
    private TrophyMetaRepository $trophyMetaRepository;

    private TrophyHistoryRecorder $historyRecorder;

    private ImageHashCalculator $imageHashCalculator;
    private PsnGameLookupService $psnGameLookupService;
    private PsnTrophyLookupGroupDataProvider $trophyLookupGroupDataProvider;
    private TrophyImageDirectories $imageDirectories;
    private TrophyImageDownloader $imageDownloader;
    private PlayStationWorkerAuthenticator $workerAuthenticator;
    private TrophyCatalogSynchronizer $trophyCatalogSynchronizer;

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
        ?TrophyCatalogSynchronizer $trophyCatalogSynchronizer = null,
    )
    {
        $this->database = $database;
        $this->trophyCalculator = $trophyCalculator;
        $this->historyRecorder = $historyRecorder ?? new TrophyHistoryRecorder($database);
        $this->trophyMetaRepository = new TrophyMetaRepository($database);
        $this->imageHashCalculator = $imageHashCalculator ?? new ImageHashCalculator();
        $this->psnGameLookupService = $psnGameLookupService ?? PsnGameLookupService::fromDatabase($database);
        $this->trophyLookupGroupDataProvider = $trophyLookupGroupDataProvider
            ?? new PsnTrophyLookupGroupDataProvider($this->psnGameLookupService);
        $this->imageDirectories = $imageDirectories ?? TrophyImageDirectories::productionDefault();
        $this->imageDownloader = $imageDownloader ?? new TrophyImageDownloader($this->imageHashCalculator);
        $this->trophyCatalogSynchronizer = $trophyCatalogSynchronizer ?? new TrophyCatalogSynchronizer($database);
        $this->workerAuthenticator = $workerAuthenticator ?? PlayStationWorkerAuthenticator::fromWorkerService(
            new WorkerService($database),
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
            ? \Closure::fromCallable($logListener)
            : null;
        $previousImageDownloader = $this->imageDownloader;
        $this->imageDownloader = $this->imageDownloader->withLogger(
            function (string $message): void {
                $this->logMessage($message);
            }
        );

        try {
            $differenceTracker = new GameRescanDifferenceTracker();
            $progressReporter = new GameRescanProgressReporter($progressListener);
            $progressReporter->notify(5, 'Validating game id…');
            $npCommunicationId = $this->getGameNpCommunicationId($gameId);

            $progressReporter->notify(10, 'Checking game entry…');

            if (!$this->isOriginalGame($npCommunicationId)) {
                return new GameRescanResult(
                    'Can only rescan original game entries.',
                    $differenceTracker->getDifferences()
                );
            }

            $progressReporter->notify(15, 'Signing in to worker account…');
            $client = $this->loginToWorker();
            $progressReporter->notify(20, 'Locating accessible player…');
            $user = $this->findAccessibleUserWithGame($client, $npCommunicationId);

            if ($user === null) {
                throw new RuntimeException('Unable to find accessible player for the specified game.');
            }

            $trophyTitle = $this->findTrophyTitleForUser($user, $npCommunicationId);

            if ($trophyTitle === null) {
                throw new RuntimeException('Unable to find trophy title for the specified game.');
            }

            $progressReporter->notify(25, 'Refreshing trophy details…');
            $trophyGroups = $this->updateTrophyTitle(
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
                (int) $user->accountId(),
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
        }
    }

    private function getGameNpCommunicationId(int $gameId): string
    {
        $query = $this->database->prepare('SELECT np_communication_id FROM trophy_title WHERE id = :id');
        $query->bindValue(':id', $gameId, PDO::PARAM_INT);
        $query->execute();

        $npCommunicationId = $query->fetchColumn();
        if ($npCommunicationId === false) {
            throw new RuntimeException('Unable to find the specified game.');
        }

        return (string) $npCommunicationId;
    }

    private function isOriginalGame(string $npCommunicationId): bool
    {
        return str_starts_with($npCommunicationId, self::ORIGINAL_GAME_PREFIX);
    }

    private function loginToWorker(): Client
    {
        /** @var Client $client */
        $client = $this->workerAuthenticator->authenticateWithRetry(
            self::LOGIN_RETRY_DELAY_SECONDS,
            function (int $workerId, Throwable $exception): void {
                $this->logMessage("Can't login with worker " . $workerId);
            },
        );

        return $client;
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

    private function findAccessibleUserWithGame(Client $client, string $npCommunicationId): ?object
    {
        $query = $this->database->prepare(
            'SELECT account_id
            FROM trophy_title_player ttp
            JOIN player p USING(account_id)
            WHERE ttp.np_communication_id = :np_communication_id
            ORDER BY ttp.last_updated_date DESC'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        while (($accountId = $query->fetchColumn()) !== false) {
            $user = $client->users()->find((string) $accountId);

            try {
                $user->trophySummary()->level();

                return $user;
            } catch (TypeError $exception) {
                // Something odd, try next player.
            } catch (Exception $exception) {
                // Player probably private, try next player.
            }
        }

        return null;
    }

    private function findTrophyTitleForUser(object $user, string $npCommunicationId): ?object
    {
        foreach ($user->trophyTitles() as $trophyTitle) {
            if ($trophyTitle->npCommunicationId() === $npCommunicationId) {
                return $trophyTitle;
            }
        }

        return null;
    }

    private function updateTrophyTitle(
        Client $client,
        object $trophyTitle,
        string $npCommunicationId,
        GameRescanProgressReporter $progressReporter,
        GameRescanDifferenceTracker $differenceTracker
    ): array {
        $existingTitleInfo = $this->trophyCatalogSynchronizer->fetchExistingTrophyTitleInfo($npCommunicationId);

        if (!self::isSetVersionAtLeastCurrent((string) $trophyTitle->trophySetVersion(), $existingTitleInfo['set_version'])) {
            $progressReporter->notify(70, 'Skipping trophy refresh because incoming set version is lower.');

            return [];
        }

        $existingGroupData = $this->trophyCatalogSynchronizer->fetchExistingTrophyGroupData($npCommunicationId);
        $existingTrophyData = $this->trophyCatalogSynchronizer->fetchExistingTrophyData($npCommunicationId);

        $titleDetail = (string) $trophyTitle->detail();
        $titleIconFilename = $this->imageDownloader->downloadMandatoryForRescan(
            $trophyTitle->iconUrl(),
            $this->imageDirectories->title,
            $existingTitleInfo['icon']
        );
        $platforms = $this->buildPlatformList($trophyTitle, $existingTitleInfo['platforms']);

        $differenceTracker->recordTitleChange('Detail', $existingTitleInfo['detail'], $titleDetail);
        $differenceTracker->recordTitleChange('Icon', $existingTitleInfo['icon'], $titleIconFilename);
        $differenceTracker->recordTitleChange('Platforms', $existingTitleInfo['platform'], $platforms);

        $query = $this->database->prepare(
            'UPDATE trophy_title
            SET detail = :detail,
                icon_url = :icon_url,
                platform = :platform
            WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':detail', $titleDetail, PDO::PARAM_STR);
        $query->bindValue(':icon_url', $titleIconFilename, PDO::PARAM_STR);
        $query->bindValue(':platform', $platforms, PDO::PARAM_STR);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $groupData = $this->trophyLookupGroupDataProvider->fetchGroupData($client, $npCommunicationId);
        $totalSteps = array_reduce(
            $groupData,
            static fn (int $carry, array $data): int => $carry + 1 + count($data['trophies']),
            0
        );

        if ($groupData === []) {
            $progressReporter->notify(70, 'Refreshing trophy details…');

            return [];
        }

        $currentStep = 0;
        $totalGroups = count($groupData);
        $processedGroups = 0;

        foreach ($groupData as $data) {
            $trophyGroup = $data['group'];
            $groupId = (string) $trophyGroup->id();
            $existingGroup = $existingGroupData[$groupId] ?? [
                'name' => null,
                'detail' => null,
                'icon' => null,
            ];

            $groupIconFilename = $this->imageDownloader->downloadMandatoryForRescan(
                $trophyGroup->iconUrl(),
                $this->imageDirectories->group,
                $existingGroup['icon']
            );

            $groupLabel = $progressReporter->describeTrophyGroup($trophyGroup);
            $contextLabel = $groupLabel !== ''
                ? $groupLabel
                : ($existingGroup['name'] ?? ($existingGroup['detail'] ?? $groupId));
            $contextLabel = (string) $contextLabel;

            $differenceTracker->recordGroupChange(
                $groupId,
                $contextLabel,
                'Detail',
                $existingGroup['detail'],
                (string) $trophyGroup->detail()
            );
            $differenceTracker->recordGroupChange(
                $groupId,
                $contextLabel,
                'Icon',
                $existingGroup['icon'],
                $groupIconFilename
            );

            $this->trophyCatalogSynchronizer->upsertTrophyGroup(
                $npCommunicationId,
                (string) $trophyGroup->id(),
                $trophyGroup->name(),
                (string) $trophyGroup->detail(),
                $groupIconFilename,
                false,
            );

            $processedGroups++;
            $currentStep++;
            $progressReporter->notifyRange(
                25,
                70,
                $currentStep,
                $totalSteps,
                sprintf(
                    'Refreshing trophy group "%s" (%d/%d groups)',
                    $contextLabel,
                    $processedGroups,
                    $totalGroups
                )
            );

            $groupTrophyCount = count($data['trophies']);
            $processedTrophiesInGroup = 0;

            foreach ($data['trophies'] as $trophy) {
                $orderId = (int) $trophy->id();
                $trophyHidden = (int) $trophy->hidden();
                $existingTrophy = $existingTrophyData[$groupId][$orderId] ?? [
                    'hidden' => null,
                    'type' => null,
                    'name' => null,
                    'detail' => null,
                    'icon' => null,
                    'progress_target_value' => null,
                    'reward_name' => null,
                    'reward_image' => null,
                ];

                $trophyIconFilename = $this->imageDownloader->downloadMandatoryForRescan(
                    $trophy->iconUrl(),
                    $this->imageDirectories->trophy,
                    $existingTrophy['icon']
                );
                $rewardImageFilename = $this->imageDownloader->downloadOptionalForRescan(
                    $trophy->rewardImageUrl(),
                    $this->imageDirectories->reward,
                    $existingTrophy['reward_image']
                );

                $trophyLabel = $progressReporter->describeTrophy($trophy);
                $trophyContextLabel = $trophyLabel !== ''
                    ? $trophyLabel
                    : ($existingTrophy['name'] ?? ('#' . $orderId));
                $trophyContextLabel = (string) $trophyContextLabel;

                $differenceTracker->recordTrophyChange(
                    $groupId,
                    $orderId,
                    $contextLabel,
                    $trophyContextLabel,
                    'Hidden',
                    $existingTrophy['hidden'],
                    $trophyHidden
                );
                $differenceTracker->recordTrophyChange(
                    $groupId,
                    $orderId,
                    $contextLabel,
                    $trophyContextLabel,
                    'Type',
                    $existingTrophy['type'],
                    $trophy->type()->value
                );
                $differenceTracker->recordTrophyChange(
                    $groupId,
                    $orderId,
                    $contextLabel,
                    $trophyContextLabel,
                    'Name',
                    $existingTrophy['name'],
                    $trophy->name()
                );
                $differenceTracker->recordTrophyChange(
                    $groupId,
                    $orderId,
                    $contextLabel,
                    $trophyContextLabel,
                    'Detail',
                    $existingTrophy['detail'],
                    $trophy->detail()
                );
                $differenceTracker->recordTrophyChange(
                    $groupId,
                    $orderId,
                    $contextLabel,
                    $trophyContextLabel,
                    'Icon',
                    $existingTrophy['icon'],
                    $trophyIconFilename
                );

                $normalizedProgressTargetValue = null;
                $progressTargetValue = $trophy->progressTargetValue();
                $normalizedProgressTargetValue = $progressTargetValue === ''
                    ? null
                    : (int) $progressTargetValue;

                $differenceTracker->recordTrophyChange(
                    $groupId,
                    $orderId,
                    $contextLabel,
                    $trophyContextLabel,
                    'Progress Target',
                    $existingTrophy['progress_target_value'],
                    $normalizedProgressTargetValue
                );

                $rewardName = $trophy->rewardName();
                $normalizedRewardName = $rewardName === '' ? null : $rewardName;

                $differenceTracker->recordTrophyChange(
                    $groupId,
                    $orderId,
                    $contextLabel,
                    $trophyContextLabel,
                    'Reward Name',
                    $existingTrophy['reward_name'],
                    $normalizedRewardName
                );
                $differenceTracker->recordTrophyChange(
                    $groupId,
                    $orderId,
                    $contextLabel,
                    $trophyContextLabel,
                    'Reward Image',
                    $existingTrophy['reward_image'],
                    $rewardImageFilename
                );

                $this->trophyCatalogSynchronizer->upsertTrophy(
                    $npCommunicationId,
                    (string) $trophyGroup->id(),
                    (int) $trophy->id(),
                    (int) $trophy->hidden(),
                    $trophy->type()->value,
                    $trophy->name(),
                    $trophy->detail(),
                    $trophyIconFilename,
                    $normalizedProgressTargetValue,
                    $normalizedRewardName,
                    $rewardImageFilename,
                );
                $this->trophyMetaRepository->ensureExists($npCommunicationId, $trophyGroup->id(), (int) $trophy->id());

                $processedTrophiesInGroup++;
                $currentStep++;
                $progressReporter->notifyRange(
                    25,
                    70,
                    $currentStep,
                    $totalSteps,
                    sprintf(
                        'Refreshing trophy "%s" in group "%s" (%d/%d trophies, group %d/%d)',
                        $trophyContextLabel,
                        $contextLabel,
                        $processedTrophiesInGroup,
                        max(1, $groupTrophyCount),
                        $processedGroups,
                        $totalGroups
                    )
                );
            }
        }

        return array_map(
            static fn (array $data) => $data['group'],
            $groupData
        );
    }

    private function recalculateTrophies(
        object $trophyTitle,
        string $npCommunicationId,
        int $accountId,
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
        int $accountId,
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

        if (!self::isSetVersionAtLeastCurrent($setVersion, $previousVersion)) {
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

    private static function isSetVersionAtLeastCurrent(string $newVersion, ?string $currentVersion): bool
    {
        $normalizedCurrentVersion = $currentVersion === null ? null : trim($currentVersion);

        if ($normalizedCurrentVersion === null || $normalizedCurrentVersion === '') {
            return true;
        }

        return version_compare(trim($newVersion), $normalizedCurrentVersion, '>=');
    }

    private function recordRescan(int $gameId): void
    {
        $query = $this->database->prepare(
            "INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_RESCAN', :param_1)"
        );
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

    /**
     * @param array<int, string> $existingPlatforms
     */
    private function buildPlatformList(object $trophyTitle, array $existingPlatforms): string
    {
        $platforms = [];
        foreach ($trophyTitle->platform() as $platform) {
            $platforms[] = $platform->value;
        }

        $platforms = array_values(array_filter($platforms, static fn(string $platform): bool => $platform !== ''));
        $platforms = array_values(array_unique($platforms));

        $existingPlatforms = array_values(array_unique(array_filter(
            $existingPlatforms,
            static fn(string $platform): bool => $platform !== ''
        )));

        if (in_array('PSVR2', $existingPlatforms, true)) {
            if (!in_array('PSVR2', $platforms, true)) {
                $platforms[] = 'PSVR2';
            }

            if (!in_array('PS5', $existingPlatforms, true)) {
                $platforms = array_values(array_filter(
                    $platforms,
                    static fn(string $platform): bool => $platform !== 'PS5'
                ));
            }
        }

        if (in_array('PSVR', $existingPlatforms, true)) {
            if (!in_array('PSVR', $platforms, true)) {
                $platforms[] = 'PSVR';
            }

            if (!in_array('PS4', $existingPlatforms, true)) {
                $platforms = array_values(array_filter(
                    $platforms,
                    static fn(string $platform): bool => $platform !== 'PS4'
                ));
            }
        }

        return implode(',', $platforms);
    }
}
