<?php

declare(strict_types=1);

require_once __DIR__ . '/GameRescanDifferenceTracker.php';
require_once __DIR__ . '/GameRescanProgressReporter.php';
require_once __DIR__ . '/GameRescanGroupDataFetcher.php';
require_once __DIR__ . '/PsnTrophyLookupGroupDataProvider.php';
require_once __DIR__ . '/../TrophyMetaRepository.php';
require_once __DIR__ . '/../TrophyImageDirectories.php';
require_once __DIR__ . '/../TrophyImageDownloader.php';
require_once __DIR__ . '/../TrophyCatalogSynchronizer.php';

use Tustin\PlayStation\Client;

/**
 * Refreshes trophy title, group, and trophy catalog rows during admin game rescans.
 */
final class GameRescanCatalogUpdater
{
    public function __construct(
        private readonly PDO $database,
        private readonly TrophyCatalogSynchronizer $trophyCatalogSynchronizer,
        private readonly TrophyMetaRepository $trophyMetaRepository,
        private readonly GameRescanGroupDataFetcher $groupDataFetcher,
        private readonly TrophyImageDirectories $imageDirectories,
        private readonly TrophyImageDownloader $imageDownloader,
    ) {
    }

    public function updateFromPsn(
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

        $groupData = $this->groupDataFetcher->fetchGroupData($client, $npCommunicationId);
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

    public static function isSetVersionAtLeastCurrent(string $newVersion, ?string $currentVersion): bool
    {
        $normalizedCurrentVersion = $currentVersion === null ? null : trim($currentVersion);

        if ($normalizedCurrentVersion === null || $normalizedCurrentVersion === '') {
            return true;
        }

        return version_compare(trim($newVersion), $normalizedCurrentVersion, '>=');
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
