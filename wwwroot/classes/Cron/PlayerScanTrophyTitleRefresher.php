<?php

declare(strict_types=1);

require_once __DIR__ . '/../Psn100Logger.php';
require_once __DIR__ . '/PlayerScanTitleMetadataHelper.php';
require_once __DIR__ . '/WorkerScanCoordinator.php';

/**
 * Validates and refreshes PSN trophy title payloads during player scans.
 *
 * Encapsulates PSN title icon and last-updated-date retry logic that was
 * previously embedded in ThirtyMinuteCronJob.
 */
final class PlayerScanTrophyTitleRefresher
{
    public function __construct(
        private readonly Psn100Logger $logger,
        private readonly PlayerScanTitleMetadataHelper $titleMetadataHelper,
        private readonly WorkerScanCoordinator $workerScanCoordinator,
    ) {
    }

    public function ensureTrophyTitleIcon(
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

            $trophyTitle = $this->refetchTrophyTitleByNpCommunicationId($user, $npCommunicationId, $trophyTitle);
        }

        return null;
    }

    public function ensureValidTrophyTitleLastUpdatedDate(
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

            $trophyTitle = $this->refetchTrophyTitleByNpCommunicationId($user, $npCommunicationId, $trophyTitle);
        }

        return null;
    }

    public function pauseBeforeRetryingInvalidApiResponse(int $workerId, string $onlineId): void
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

    private function refetchTrophyTitleByNpCommunicationId(
        object $user,
        string $npCommunicationId,
        object $fallbackTitle
    ): object {
        $trophyTitles = iterator_to_array($user->trophyTitles(), false);

        return array_find(
            $trophyTitles,
            static fn (object $refreshedTitle): bool => (string) $refreshedTitle->npCommunicationId() === $npCommunicationId
        ) ?? $fallbackTitle;
    }
}
