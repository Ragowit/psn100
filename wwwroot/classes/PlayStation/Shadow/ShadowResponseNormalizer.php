<?php

declare(strict_types=1);

final class ShadowResponseNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public static function normalizePlayerProfileLookup(mixed $payload): array
    {
        $normalized = self::canonicalize($payload);

        if (isset($normalized['profile']) && is_array($normalized['profile'])) {
            $profile = $normalized['profile'];
            $profile['accountId'] = self::normalizeNullableScalar($profile['accountId'] ?? null, true);
            $profile['onlineId'] = self::normalizeNullableScalar($profile['onlineId'] ?? null, false);
            $profile['currentOnlineId'] = self::normalizeNullableScalar($profile['currentOnlineId'] ?? null, false);
            $profile['npId'] = self::normalizeNullableScalar($profile['npId'] ?? null, false);
            ksort($profile);
            $normalized['profile'] = $profile;
        }

        return self::canonicalize($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalizeTrophyLookup(mixed $payload): array
    {
        $normalized = self::canonicalize($payload);

        if (isset($normalized['trophyGroups']) && is_array($normalized['trophyGroups'])) {
            $groups = [];

            foreach ($normalized['trophyGroups'] as $group) {
                if (!is_array($group)) {
                    continue;
                }

                $group['trophyGroupId'] = (string) ($group['trophyGroupId'] ?? '');
                $group['trophyGroupName'] = (string) ($group['trophyGroupName'] ?? '');
                $group['trophyGroupDetail'] = (string) ($group['trophyGroupDetail'] ?? '');
                $group['trophyGroupIconUrl'] = (string) ($group['trophyGroupIconUrl'] ?? '');

                if (isset($group['trophies']) && is_array($group['trophies'])) {
                    $group['trophies'] = array_map(static function (mixed $trophy): array {
                        if (!is_array($trophy)) {
                            return [];
                        }

                        if (array_key_exists('trophyId', $trophy)) {
                            $trophy['trophyId'] = self::normalizeNullableScalar($trophy['trophyId'], true);
                        }

                        return self::canonicalize($trophy);
                    }, $group['trophies']);
                } else {
                    $group['trophies'] = [];
                }

                $groups[] = self::canonicalize($group);
            }

            $normalized['trophyGroups'] = $groups;
        }

        return self::canonicalize($normalized);
    }

    /**
     * @return array<string, mixed>|list<mixed>|int|string|float|bool|null
     */
    public static function canonicalize(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return self::normalizeNullableScalar($value, true);
        }

        $isList = array_is_list($value);
        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = self::canonicalize($item);
        }

        if (!$isList) {
            ksort($normalized);
        }

        return $normalized;
    }

    /**
     * @return int|string|float|bool|null
     */
    private static function normalizeNullableScalar(mixed $value, bool $coerceNumericString): mixed
    {
        if ($value === '') {
            return null;
        }

        if ($coerceNumericString && is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return $value;
    }
}
