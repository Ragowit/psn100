<?php

declare(strict_types=1);

require_once __DIR__ . '/GameDetail.php';
require_once __DIR__ . '/../CommaSeparatedValues.php';
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

        return $value |> trim(...) |> strtolower(...);
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
            $candidates = CommaSeparatedValues::parseUppercaseTrimmed($value);
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

        return self::PLATFORM_OPTIONS
            |> (fn(array $platforms): array => array_filter(
                $platforms,
                static fn (string $platform): bool => isset($selected[$platform])
            ))
            |> array_values(...)
            |> (fn(array $platforms): string => implode(',', $platforms));
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

        $normalized = CommaSeparatedValues::parseTrimmed($value)
            |> (fn(array $segments): array => array_filter($segments, ctype_digit(...)))
            |> (fn(array $segments): array => array_map(
                static fn(string $segment): string => (string) (int) $segment,
                $segments
            ))
            |> array_unique(...)
            |> array_values(...);

        if ($normalized === []) {
            return null;
        }

        return implode(',', $normalized);
    }
}
