<?php

declare(strict_types=1);

require_once __DIR__ . '/../Admin/PsnGameLookupService.php';
require_once __DIR__ . '/../AutomaticTrophyTitleMergeService.php';
require_once __DIR__ . '/../ImageHashCalculator.php';
require_once __DIR__ . '/../Psn100Logger.php';
require_once __DIR__ . '/../TrophyMergeService.php';
require_once __DIR__ . '/../TrophyCatalogSynchronizer.php';
require_once __DIR__ . '/../TrophyHistoryRecorder.php';
require_once __DIR__ . '/../TrophyImageDirectories.php';
require_once __DIR__ . '/../TrophyImageDownloader.php';
require_once __DIR__ . '/../TrophyMetaRepository.php';
require_once __DIR__ . '/../TrophyTitleNameFormatter.php';
require_once __DIR__ . '/PlayerScanTitleCatalogSyncResult.php';
require_once __DIR__ . '/PlayerScanTitleMetadataHelper.php';

/**
 * Persists trophy catalog rows for a single title during player scans.
 *
 * Encapsulates per-title catalog synchronization that was previously embedded in
 * ThirtyMinuteCronJob.
 */
final class PlayerScanTitleCatalogSynchronizer
{
    /**
     * @param null|callable(string, object): array<string, mixed> $trophyDataFetcher
     */
    public function __construct(
        private readonly PDO $database,
        private readonly Psn100Logger $logger,
        private readonly ?PsnGameLookupService $psnGameLookupService = null,
        private readonly ?TrophyImageDirectories $imageDirectories = null,
        private readonly ?TrophyImageDownloader $imageDownloader = null,
        private readonly ?TrophyTitleNameFormatter $trophyTitleNameFormatter = null,
        private readonly ?TrophyCatalogSynchronizer $trophyCatalogSynchronizer = null,
        private readonly ?PlayerScanTitleMetadataHelper $titleMetadataHelper = null,
        private readonly ?TrophyMetaRepository $trophyMetaRepository = null,
        private readonly ?TrophyHistoryRecorder $historyRecorder = null,
        private readonly ?AutomaticTrophyTitleMergeService $automaticTrophyTitleMergeService = null,
        private readonly mixed $trophyDataFetcher = null,
    ) {
    }

    public function synchronizeCatalog(object $trophyTitle, object $client): PlayerScanTitleCatalogSyncResult
    {
        $npid = (string) $trophyTitle->npCommunicationId();
        $newTrophies = false;
        $titleDataChanged = false;
        $groupDataChanged = false;
        $trophyDataChanged = false;
        $titleId = null;

        $platforms = $this->formatPlatformListFromTitle($trophyTitle);

        $sanitizedTitleName = $this->titleFormatter()->format($trophyTitle->name());

        $existingTitle = $this->catalogSynchronizer()->fetchExistingTrophyTitleRow($npid);
        $isNewTitle = $existingTitle === null;
        $incomingSetVersion = $trophyTitle->trophySetVersion();
        $setVersionForUpdate = $this->metadataHelper()->resolveSetVersionForUpdate(
            $incomingSetVersion,
            is_array($existingTitle) ? ($existingTitle['set_version'] ?? null) : null
        );
        $incomingVersionIsOlderThanStored = $this->metadataHelper()->isIncomingSetVersionOlderThanStored(
            $incomingSetVersion,
            is_array($existingTitle) ? ($existingTitle['set_version'] ?? null) : null
        );

        $previousTitleIconFilename = $existingTitle['icon_url'] ?? null;
        $titleIconFilename = $previousTitleIconFilename;
        $directories = $this->directories();
        $titleIconMissing = $titleIconFilename === null
            || !file_exists($directories->title . $titleIconFilename);

        $titleNeedsUpdate = $existingTitle === null
            || (
                !$incomingVersionIsOlderThanStored
                && (
                    $existingTitle['detail'] !== $trophyTitle->detail()
                    || $existingTitle['set_version'] !== $setVersionForUpdate
                )
            );

        if ($existingTitle === null || $titleNeedsUpdate || $titleIconMissing) {
            $titleIconFilename = $this->downloader()->downloadMandatoryForScan(
                $trophyTitle->iconUrl(),
                $directories->title,
                sprintf('title icon for "%s" (%s)', $trophyTitle->name(), $npid),
                $previousTitleIconFilename
            );
        }

        if ($existingTitle === null || $titleNeedsUpdate || $titleIconMissing) {
            $titleAffectedRows = $this->catalogSynchronizer()->upsertTrophyTitle(
                $npid,
                $sanitizedTitleName,
                $trophyTitle->detail(),
                $titleIconFilename,
                $platforms,
                $setVersionForUpdate,
                $incomingVersionIsOlderThanStored,
            );

            if ($titleAffectedRows > 0) {
                $titleDataChanged = true;
            }
        }

        $metaQuery = $this->database->prepare('INSERT IGNORE INTO trophy_title_meta (
                np_communication_id,
                message
            )
            VALUES (
                :np_communication_id,
                :message
            )');
        $metaQuery->bindValue(':np_communication_id', $npid, PDO::PARAM_STR);
        $metaQuery->bindValue(':message', '', PDO::PARAM_STR);
        $metaQuery->execute();

        try {
            $trophyData = $this->fetchTrophyData($npid, $client);
        } catch (Throwable $exception) {
            $this->logger->log(sprintf(
                'Unable to fetch trophy data for %s (%s): %s',
                $trophyTitle->name(),
                $npid,
                $exception->getMessage()
            ));

            return PlayerScanTitleCatalogSyncResult::restartScan();
        }

        $trophyGroups = $trophyData['trophyGroups'] ?? [];
        if (!is_array($trophyGroups)) {
            $trophyGroups = [];
        }

        $topLevelTrophies = $trophyData['trophies'] ?? [];
        if (!is_array($topLevelTrophies)) {
            $topLevelTrophies = [];
        }

        $fallbackGroupTrophies = [];
        foreach ($topLevelTrophies as $topLevelTrophy) {
            if (!is_array($topLevelTrophy)) {
                continue;
            }

            $topLevelTrophyGroupId = (string) ($topLevelTrophy['trophyGroupId'] ?? '');
            if ($topLevelTrophyGroupId === '') {
                continue;
            }

            $fallbackGroupTrophies[$topLevelTrophyGroupId][] = $topLevelTrophy;
        }

        foreach ($trophyGroups as $trophyGroup) {
            if (!is_array($trophyGroup)) {
                continue;
            }

            $trophyGroupId = (string) ($trophyGroup['trophyGroupId'] ?? '');
            if ($trophyGroupId === '') {
                continue;
            }

            $trophyGroupName = (string) ($trophyGroup['trophyGroupName'] ?? '');
            $trophyGroupDetail = (string) ($trophyGroup['trophyGroupDetail'] ?? '');
            $trophyGroupIconUrl = (string) ($trophyGroup['trophyGroupIconUrl'] ?? '');
            $groupNewTrophies = false;
            $existingGroup = $this->catalogSynchronizer()->fetchExistingTrophyGroup($npid, $trophyGroupId);

            $previousGroupIconFilename = $existingGroup['icon_url'] ?? null;
            $groupIconFilename = $previousGroupIconFilename;
            $groupIconMissing = $groupIconFilename === null
                || !file_exists($directories->group . $groupIconFilename);

            $groupNeedsUpdate = $existingGroup === null
                || $existingGroup['name'] !== $trophyGroupName
                || $existingGroup['detail'] !== $trophyGroupDetail;

            if ($existingGroup === null || $groupNeedsUpdate || $groupIconMissing || $titleNeedsUpdate) {
                $groupIconFilename = $this->downloader()->downloadMandatoryForScan(
                    $trophyGroupIconUrl,
                    $directories->group,
                    sprintf('trophy group icon for "%s" (%s/%s)', $trophyGroupName, $npid, $trophyGroupId),
                    $previousGroupIconFilename
                );
            }

            if ($existingGroup === null || $groupNeedsUpdate || $groupIconMissing || $titleNeedsUpdate) {
                $groupAffectedRows = $this->catalogSynchronizer()->upsertTrophyGroup(
                    $npid,
                    $trophyGroupId,
                    $trophyGroupName,
                    $trophyGroupDetail,
                    $groupIconFilename,
                );

                if ($groupAffectedRows > 0) {
                    $groupDataChanged = true;
                }
            }

            $groupTrophies = $trophyGroup['trophies'] ?? [];
            if (!is_array($groupTrophies)) {
                $groupTrophies = [];
            }

            if ($groupTrophies === [] && isset($fallbackGroupTrophies[$trophyGroupId])) {
                $groupTrophies = $fallbackGroupTrophies[$trophyGroupId];
            }

            if ($groupTrophies === []) {
                $this->logger->log(sprintf(
                    'Unable to sync trophies for %s (%s/%s): trophy group payload did not include any trophies.',
                    $trophyTitle->name(),
                    $npid,
                    $trophyGroupId
                ));

                return PlayerScanTitleCatalogSyncResult::restartScan();
            }

            foreach ($groupTrophies as $trophy) {
                if (!is_array($trophy)) {
                    continue;
                }

                $rawTrophyOrderId = $trophy['trophyId'] ?? null;
                if (!is_numeric($rawTrophyOrderId)) {
                    continue;
                }

                $trophyOrderId = (int) $rawTrophyOrderId;
                if ($trophyOrderId < 0) {
                    continue;
                }

                $existingTrophy = $this->catalogSynchronizer()->fetchExistingTrophy(
                    $npid,
                    $trophyGroupId,
                    $trophyOrderId,
                );

                $trophyHidden = (int) ($trophy['trophyHidden'] ?? 0);

                $rawProgressTargetValue = $trophy['trophyProgressTargetValue'] ?? null;

                $existingProgressTargetValue = null;
                $existingRewardName = null;
                $existingRewardImageFilename = null;
                $existingIconFilename = null;

                if ($existingTrophy !== null) {
                    $existingProgressTargetValue = $existingTrophy['progress_target_value'] === null
                        ? null
                        : (int) $existingTrophy['progress_target_value'];
                    $existingRewardName = $existingTrophy['reward_name'];
                    $existingRewardImageFilename = $existingTrophy['reward_image_url'];
                    $existingIconFilename = $existingTrophy['icon_url'];
                }

                $progressTargetValue = $rawProgressTargetValue === null || $rawProgressTargetValue === ''
                    ? null
                    : (int) $rawProgressTargetValue;

                $rewardName = (string) ($trophy['trophyRewardName'] ?? '');
                $rewardName = $rewardName === '' ? null : $rewardName;

                $rewardImageUrl = $trophy['trophyRewardImageUrl'] ?? null;
                $rewardImageShouldBeNull = $rewardImageUrl === null || $rewardImageUrl === '';

                $trophyTypeEnumValue = strtolower((string) ($trophy['trophyType'] ?? ''));
                if ($trophyTypeEnumValue === '') {
                    $trophyTypeEnumValue = 'bronze';
                }

                $trophyName = (string) ($trophy['trophyName'] ?? '');
                $trophyDetail = (string) ($trophy['trophyDetail'] ?? '');
                $trophyIconUrl = (string) ($trophy['trophyIconUrl'] ?? '');

                $trophyNeedsUpdate = $existingTrophy === null
                    || (int) ($existingTrophy['hidden'] ?? -1) !== $trophyHidden
                    || ($existingTrophy['type'] ?? '') !== $trophyTypeEnumValue
                    || ($existingTrophy['name'] ?? '') !== $trophyName
                    || ($existingTrophy['detail'] ?? '') !== $trophyDetail
                    || $existingProgressTargetValue !== $progressTargetValue
                    || ($existingRewardName !== $rewardName)
                    || ($rewardImageShouldBeNull && $existingRewardImageFilename !== null)
                    || (!$rewardImageShouldBeNull && $existingRewardImageFilename === null);

                $iconMissing = $existingIconFilename === null
                    || !file_exists($directories->trophy . $existingIconFilename);

                $previousIconFilename = $existingIconFilename;

                if ($existingTrophy === null || $trophyNeedsUpdate || $iconMissing || $groupNeedsUpdate || $titleNeedsUpdate) {
                    $trophyIconFilename = $this->downloader()->downloadMandatoryForScan(
                        $trophyIconUrl,
                        $directories->trophy,
                        sprintf(
                            'trophy icon for "%s" (%s/%s/%d)',
                            $trophyName,
                            $npid,
                            $trophyGroupId,
                            $trophyOrderId
                        ),
                        $previousIconFilename
                    );
                } else {
                    $trophyIconFilename = $existingIconFilename;
                }

                $rewardImageMissing = false;

                if ($rewardImageShouldBeNull) {
                    $rewardImageFilename = null;
                } else {
                    $rewardImageMissing = $existingRewardImageFilename === null
                        || !file_exists($directories->reward . $existingRewardImageFilename);

                    if ($existingTrophy === null || $trophyNeedsUpdate || $rewardImageMissing || $groupNeedsUpdate || $titleNeedsUpdate) {
                        $rewardImageFilename = $this->downloader()->downloadOptionalForScan(
                            $rewardImageUrl === null ? '' : (string) $rewardImageUrl,
                            $directories->reward,
                            sprintf(
                                'reward image for "%s" (%s/%s/%d)',
                                $trophyName,
                                $npid,
                                $trophyGroupId,
                                $trophyOrderId
                            ),
                            $existingRewardImageFilename
                        );
                    } else {
                        $rewardImageFilename = $existingRewardImageFilename;
                    }
                }

                $shouldUpsertTrophy = $existingTrophy === null
                    || $trophyNeedsUpdate
                    || $iconMissing
                    || (!$rewardImageShouldBeNull && $rewardImageMissing);

                if ($shouldUpsertTrophy) {
                    $trophyAffectedRows = $this->catalogSynchronizer()->upsertTrophy(
                        $npid,
                        $trophyGroupId,
                        $trophyOrderId,
                        $trophyHidden,
                        $trophyTypeEnumValue,
                        $trophyName,
                        $trophyDetail,
                        $trophyIconFilename,
                        $progressTargetValue,
                        $rewardName,
                        $rewardImageFilename,
                    );

                    if ($trophyAffectedRows > 0) {
                        $trophyDataChanged = true;
                    }

                    if ($trophyAffectedRows === 1) {
                        $newTrophies = true;
                        $groupNewTrophies = true;
                    }
                }

                $this->trophyMetaRepository()->ensureExists($npid, $trophyGroupId, $trophyOrderId);
            }

            if ($groupNewTrophies) {
                $query = $this->database->prepare('SELECT status
                    FROM   trophy_title_meta
                    WHERE  np_communication_id = :np_communication_id ');
                $query->bindValue(':np_communication_id', $npid, PDO::PARAM_STR);
                $query->execute();
                $status = $query->fetchColumn();
                if ($status == 2) {
                    $this->logger->log('New trophies added for ' . $trophyTitle->name() . '. ' . $npid . ', ' . $trophyGroupId . ', ' . $trophyGroupName);
                } else {
                    $this->logger->log('SET VERSION for ' . $trophyTitle->name() . '. ' . $npid . ', ' . $trophyGroupId . ', ' . $trophyGroupName);
                }
            }
        }

        if ($titleDataChanged || $groupDataChanged || $trophyDataChanged) {
            if ($titleId === null) {
                $titleId = $this->findTrophyTitleId($npid);
            }

            if ($titleId !== null) {
                $this->historyRecorder()->recordByTitleId($titleId);
            }
        }

        $mergeParentsToRecompute = [];
        if ($isNewTitle) {
            $mergeParentsToRecompute = $this->automaticMergeService()->handleNewTitle($npid);
        }

        if ($newTrophies) {
            if ($titleId === null) {
                $titleId = $this->findTrophyTitleId($npid);
            }

            if ($titleId === null) {
                return PlayerScanTitleCatalogSyncResult::synced(
                    $newTrophies,
                    $isNewTitle,
                    null,
                    $mergeParentsToRecompute,
                );
            }

            $query = $this->database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_VERSION', :param_1)");
            $query->bindValue(':param_1', $titleId, PDO::PARAM_INT);
            $query->execute();
        }

        return PlayerScanTitleCatalogSyncResult::synced(
            $newTrophies,
            $isNewTitle,
            $titleId,
            $mergeParentsToRecompute,
        );
    }

    public function formatPlatformListFromTitle(object $trophyTitle): string
    {
        $platforms = '';
        foreach ($trophyTitle->platform() as $platform) {
            $platformValue = $platform->value;
            if ($platformValue === 'PSPC') {
                $platformValue = 'PC';
            }

            $platforms .= $platformValue . ',';
        }

        return rtrim($platforms, ',');
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchTrophyData(string $npCommunicationId, object $client): array
    {
        if ($this->trophyDataFetcher !== null) {
            return ($this->trophyDataFetcher)($npCommunicationId, $client);
        }

        return $this->psnGameLookup()->fetchTrophyDataForNpCommunicationId($npCommunicationId, $client);
    }

    private function psnGameLookup(): PsnGameLookupService
    {
        return $this->psnGameLookupService ?? PsnGameLookupService::fromDatabase($this->database);
    }

    private function findTrophyTitleId(string $npCommunicationId): ?int
    {
        $query = $this->database->prepare(
            'SELECT id FROM trophy_title WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $id = $query->fetchColumn();

        if ($id === false) {
            return null;
        }

        return (int) $id;
    }

    private function directories(): TrophyImageDirectories
    {
        return $this->imageDirectories ?? TrophyImageDirectories::productionDefault();
    }

    private function downloader(): TrophyImageDownloader
    {
        return $this->imageDownloader ?? new TrophyImageDownloader(
            new ImageHashCalculator(),
            function (string $message): void {
                $this->logger->log($message);
            },
        );
    }

    private function titleFormatter(): TrophyTitleNameFormatter
    {
        return $this->trophyTitleNameFormatter ?? new TrophyTitleNameFormatter();
    }

    private function catalogSynchronizer(): TrophyCatalogSynchronizer
    {
        return $this->trophyCatalogSynchronizer ?? new TrophyCatalogSynchronizer($this->database);
    }

    private function metadataHelper(): PlayerScanTitleMetadataHelper
    {
        return $this->titleMetadataHelper ?? new PlayerScanTitleMetadataHelper();
    }

    private function trophyMetaRepository(): TrophyMetaRepository
    {
        return $this->trophyMetaRepository ?? new TrophyMetaRepository($this->database);
    }

    private function historyRecorder(): TrophyHistoryRecorder
    {
        return $this->historyRecorder ?? new TrophyHistoryRecorder($this->database);
    }

    private function automaticMergeService(): AutomaticTrophyTitleMergeService
    {
        return $this->automaticTrophyTitleMergeService
            ?? new AutomaticTrophyTitleMergeService($this->database, new TrophyMergeService($this->database));
    }
}
