<?php

declare(strict_types=1);

require_once __DIR__ . '/../Admin/PsnGameLookupService.php';
require_once __DIR__ . '/../ImageHashCalculator.php';
require_once __DIR__ . '/../Psn100Logger.php';
require_once __DIR__ . '/../TrophyCatalogSynchronizer.php';
require_once __DIR__ . '/../TrophyImageDirectories.php';
require_once __DIR__ . '/../TrophyImageDownloader.php';
require_once __DIR__ . '/../TrophyMetaRepository.php';
require_once __DIR__ . '/../TrophyTitleNameFormatter.php';
require_once __DIR__ . '/PlayerScanCatalogSideEffects.php';
require_once __DIR__ . '/PlayerScanTitleCatalogSyncResult.php';
require_once __DIR__ . '/PlayerScanTitleHeaderSynchronizer.php';
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
        private readonly ?PlayerScanCatalogSideEffects $catalogSideEffects = null,
        private readonly mixed $trophyDataFetcher = null,
        private readonly ?PlayerScanTitleHeaderSynchronizer $titleHeaderSynchronizer = null,
    ) {
    }

    public function synchronizeCatalog(object $trophyTitle, object $client): PlayerScanTitleCatalogSyncResult
    {
        $npid = (string) $trophyTitle->npCommunicationId();
        $newTrophies = false;
        $groupDataChanged = false;
        $trophyDataChanged = false;
        $titleId = null;
        $directories = $this->directories();

        $headerSyncResult = $this->titleHeaderSynchronizer()->sync($trophyTitle);
        $titleDataChanged = $headerSyncResult->titleDataChanged;
        $titleNeedsUpdate = $headerSyncResult->titleNeedsUpdate;
        $isNewTitle = $headerSyncResult->isNewTitle;

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
                if ((int) $status === 2) {
                    $this->logger->log('New trophies added for ' . $trophyTitle->name() . '. ' . $npid . ', ' . $trophyGroupId . ', ' . $trophyGroupName);
                } else {
                    $this->logger->log('SET VERSION for ' . $trophyTitle->name() . '. ' . $npid . ', ' . $trophyGroupId . ', ' . $trophyGroupName);
                }
            }
        }

        $sideEffectResult = $this->catalogSideEffects()->applyAfterCatalogSync(
            $npid,
            $titleDataChanged,
            $groupDataChanged,
            $trophyDataChanged,
            $isNewTitle,
            $newTrophies,
            $titleId,
        );

        return PlayerScanTitleCatalogSyncResult::synced(
            $newTrophies,
            $isNewTitle,
            $sideEffectResult->titleId,
            $sideEffectResult->mergeParentsToRecompute,
        );
    }

    public function formatPlatformListFromTitle(object $trophyTitle): string
    {
        return $this->titleHeaderSynchronizer()->formatPlatformListFromTitle($trophyTitle);
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

    private function titleHeaderSynchronizer(): PlayerScanTitleHeaderSynchronizer
    {
        return $this->titleHeaderSynchronizer ?? new PlayerScanTitleHeaderSynchronizer(
            $this->database,
            $this->catalogSynchronizer(),
            $this->directories(),
            $this->downloader(),
            $this->trophyTitleNameFormatter,
            $this->titleMetadataHelper,
        );
    }

    private function catalogSynchronizer(): TrophyCatalogSynchronizer
    {
        return $this->trophyCatalogSynchronizer ?? new TrophyCatalogSynchronizer($this->database);
    }

    private function trophyMetaRepository(): TrophyMetaRepository
    {
        return $this->trophyMetaRepository ?? new TrophyMetaRepository($this->database);
    }

    private function catalogSideEffects(): PlayerScanCatalogSideEffects
    {
        return $this->catalogSideEffects ?? new PlayerScanCatalogSideEffects($this->database);
    }
}
