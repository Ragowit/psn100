<?php

declare(strict_types=1);

require_once __DIR__ . '/GameDetail.php';
require_once __DIR__ . '/../GameAvailabilityStatus.php';

final class GameDetailFormParser
{
    /**
     * @var list<string>
     */
    public const array PLATFORM_OPTIONS = [
        'PS3',
        'PSVITA',
        'PS4',
        'PSVR',
        'PS5',
        'PSVR2',
        'PC',
    ];

    /**
     * @return list<string>
     */
    public function getPlatformOptions(): array
    {
        return self::PLATFORM_OPTIONS;
    }

    public function parseNpCommunicationId(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public function parseGameId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] === '-') {
            return null;
        }

        if (!ctype_digit($trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }

    public function parseAction(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);

        return $trimmed === '' ? '' : strtolower($trimmed);
    }

    public function parseStatus(mixed $value): ?GameAvailabilityStatus
    {
        if (is_int($value)) {
            return GameAvailabilityStatus::tryFrom($value);
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed[0] === '-') {
            return null;
        }

        if (!ctype_digit($trimmed)) {
            return null;
        }

        return GameAvailabilityStatus::tryFrom((int) $trimmed);
    }

    /**
     * @param array<string, mixed> $postData
     */
    public function createGameDetailFromPost(int $gameId, array $postData, GameAvailabilityStatus $status): GameDetail
    {
        $npCommunicationId = $this->normalizeOptionalString($postData['np_communication_id'] ?? null);
        $region = $this->normalizeOptionalString($postData['region'] ?? null);
        $psnprofilesId = $this->normalizeOptionalString($postData['psnprofiles_id'] ?? null);
        $obsoleteIds = $this->normalizeObsoleteIds($postData['obsolete_ids'] ?? null);

        return new GameDetail(
            $gameId,
            $npCommunicationId,
            (string) ($postData['name'] ?? ''),
            (string) ($postData['icon_url'] ?? ''),
            $this->normalizePlatforms($postData['platform'] ?? null),
            (string) ($postData['message'] ?? ''),
            (string) ($postData['set_version'] ?? ''),
            $region,
            $psnprofilesId,
            $status,
            $obsoleteIds
        );
    }

    public function normalizePlatforms(mixed $value): string
    {
        $candidates = [];

        if (is_array($value)) {
            $candidates = $value;
        } elseif (is_string($value)) {
            $candidates = explode(',', $value);
        }

        $selected = [];
        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $platform = $candidate |> trim(...) |> strtoupper(...);
            if ($platform === '') {
                continue;
            }

            $selected[$platform] = true;
        }

        $ordered = [];
        foreach (self::PLATFORM_OPTIONS as $platform) {
            if (isset($selected[$platform])) {
                $ordered[] = $platform;
            }
        }

        return implode(',', $ordered);
    }

    public function normalizeOptionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public function normalizeObsoleteIds(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $segments = array_filter(
            array_map(static fn(string $segment): string => trim($segment), explode(',', $value)),
            static fn(string $segment): bool => $segment !== ''
        );

        if ($segments === []) {
            return null;
        }

        $normalized = [];
        foreach ($segments as $segment) {
            if (!ctype_digit($segment)) {
                continue;
            }

            $normalized[] = (string) (int) $segment;
        }

        if ($normalized === []) {
            return null;
        }

        $normalized = array_values(array_unique($normalized));

        return implode(',', $normalized);
    }
}
