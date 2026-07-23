<?php

declare(strict_types=1);

/**
 * Pure helpers for Sony trophy title timestamps, set versions, and invalid-date retry
 * tracking used during player scans.
 *
 * Extracted from ThirtyMinuteCronJob so timestamp/set-version rules can be tested
 * without reflection on the cron orchestrator.
 */
final class PlayerScanTitleMetadataHelper
{
    public function gameTimestampsMatch(string $sonyTimestamp, string $dbTimestamp): bool
    {
        $sonyLastUpdatedDate = $this->parseDateTime($sonyTimestamp);

        if ($sonyLastUpdatedDate === null) {
            return false;
        }

        $dbLastUpdatedDate = $this->parseDateTime($dbTimestamp);

        if ($dbLastUpdatedDate === null) {
            return false;
        }

        return $sonyLastUpdatedDate->getTimestamp() === $dbLastUpdatedDate->getTimestamp()
            && $sonyLastUpdatedDate->format('u') === $dbLastUpdatedDate->format('u');
    }

    public function isValidSonyLastUpdatedDateTime(string $value): bool
    {
        return $this->formatDateTimeForDatabase($value) !== null;
    }

    public function buildInvalidTitleDateRetryKey(string $onlineId, string $npCommunicationId): string
    {
        return $onlineId . ':' . $npCommunicationId;
    }

    /**
     * @param array<string, bool> $retryTracker
     */
    public function shouldRetryInvalidTitleLastUpdatedDate(
        array $retryTracker,
        string $onlineId,
        string $npCommunicationId
    ): bool {
        return !($retryTracker[$this->buildInvalidTitleDateRetryKey($onlineId, $npCommunicationId)] ?? false);
    }

    /**
     * @param array<string, bool> $retryTracker
     */
    public function markInvalidTitleLastUpdatedDateRetried(
        array &$retryTracker,
        string $onlineId,
        string $npCommunicationId
    ): void {
        $retryTracker[$this->buildInvalidTitleDateRetryKey($onlineId, $npCommunicationId)] = true;
    }

    /**
     * @param array<string, bool> $retryTracker
     */
    public function clearInvalidTitleDateRetriesForPlayer(array &$retryTracker, string $onlineId): void
    {
        $prefix = $onlineId . ':';

        foreach (array_keys($retryTracker) as $retryKey) {
            if (str_starts_with($retryKey, $prefix)) {
                unset($retryTracker[$retryKey]);
            }
        }
    }

    public function formatDateTimeForDatabase(?string $value): ?string
    {
        $dateTime = $this->parseDateTime($value);

        return $dateTime?->format('Y-m-d H:i:s');
    }

    public function parseDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    public function isIncomingSetVersionOlderThanStored(string $newVersion, mixed $currentVersion): bool
    {
        $normalizedCurrentVersion = $this->normalizeSetVersion($currentVersion);

        if ($normalizedCurrentVersion === null) {
            return false;
        }

        return version_compare(trim($newVersion), $normalizedCurrentVersion, '<');
    }

    public function resolveSetVersionForUpdate(string $newVersion, mixed $currentVersion): string
    {
        $normalizedCurrentVersion = $this->normalizeSetVersion($currentVersion);

        if ($normalizedCurrentVersion === null) {
            return trim($newVersion);
        }

        if (version_compare(trim($newVersion), $normalizedCurrentVersion, '<')) {
            return $normalizedCurrentVersion;
        }

        return trim($newVersion);
    }

    public function normalizeSetVersion(mixed $version): ?string
    {
        if (!is_string($version)) {
            return null;
        }

        $trimmedVersion = trim($version);

        if ($trimmedVersion === '') {
            return null;
        }

        return $trimmedVersion;
    }
}
