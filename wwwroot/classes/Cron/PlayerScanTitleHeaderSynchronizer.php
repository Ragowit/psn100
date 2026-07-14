<?php

declare(strict_types=1);

require_once __DIR__ . '/../TrophyCatalogSynchronizer.php';
require_once __DIR__ . '/../TrophyImageDirectories.php';
require_once __DIR__ . '/../TrophyImageDownloader.php';
require_once __DIR__ . '/../TrophyTitleNameFormatter.php';
require_once __DIR__ . '/PlayerScanTitleHeaderSyncResult.php';
require_once __DIR__ . '/PlayerScanTitleMetadataHelper.php';

/**
 * Synchronizes trophy title header rows and ensures trophy_title_meta exists.
 *
 * Extracted from PlayerScanTitleCatalogSynchronizer so per-title catalog sync can
 * delegate title-row persistence separately from group and trophy iteration.
 */
final class PlayerScanTitleHeaderSynchronizer
{
    public function __construct(
        private readonly PDO $database,
        private readonly TrophyCatalogSynchronizer $catalogSynchronizer,
        private readonly TrophyImageDirectories $imageDirectories,
        private readonly TrophyImageDownloader $imageDownloader,
        private readonly ?TrophyTitleNameFormatter $trophyTitleNameFormatter = null,
        private readonly ?PlayerScanTitleMetadataHelper $titleMetadataHelper = null,
    ) {
    }

    public function sync(object $trophyTitle): PlayerScanTitleHeaderSyncResult
    {
        $npid = (string) $trophyTitle->npCommunicationId();
        $titleDataChanged = false;

        $platforms = $this->formatPlatformListFromTitle($trophyTitle);
        $sanitizedTitleName = $this->titleFormatter()->format($trophyTitle->name());

        $existingTitle = $this->catalogSynchronizer->fetchExistingTrophyTitleRow($npid);
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
        $titleIconMissing = $titleIconFilename === null
            || !file_exists($this->imageDirectories->title . $titleIconFilename);

        $titleNeedsUpdate = $existingTitle === null
            || (
                !$incomingVersionIsOlderThanStored
                && (
                    $existingTitle['detail'] !== $trophyTitle->detail()
                    || $existingTitle['set_version'] !== $setVersionForUpdate
                )
            );

        if ($existingTitle === null || $titleNeedsUpdate || $titleIconMissing) {
            $titleIconFilename = $this->imageDownloader->downloadMandatoryForScan(
                $trophyTitle->iconUrl(),
                $this->imageDirectories->title,
                sprintf('title icon for "%s" (%s)', $trophyTitle->name(), $npid),
                $previousTitleIconFilename
            );
        }

        if ($existingTitle === null || $titleNeedsUpdate || $titleIconMissing) {
            $titleAffectedRows = $this->catalogSynchronizer->upsertTrophyTitle(
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

        return new PlayerScanTitleHeaderSyncResult(
            $titleDataChanged,
            $titleNeedsUpdate,
            $isNewTitle,
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

    private function titleFormatter(): TrophyTitleNameFormatter
    {
        return $this->trophyTitleNameFormatter ?? new TrophyTitleNameFormatter();
    }

    private function metadataHelper(): PlayerScanTitleMetadataHelper
    {
        return $this->titleMetadataHelper ?? new PlayerScanTitleMetadataHelper();
    }
}
