<?php

declare(strict_types=1);

/**
 * Assembles PSN trophy payloads into a consistent trophyGroups structure.
 *
 * Flat trophies (from all/trophies) are preferred; when absent, nested trophies
 * under trophyGroups are used. Group metadata from the trophyGroups endpoint
 * wins when present.
 */
final class PsnTrophyGroupAssembler
{
    /**
     * @return array<int, array{trophyGroupId: string, trophyGroupName: string, trophyGroupDetail: string, trophyGroupIconUrl: string, trophies: array<int, array<string, mixed>>}>
     */
    public function assemble(
        mixed $flatTrophies,
        mixed $nestedGroupsFromTrophiesPayload,
        mixed $rawGroupsFromGroupsEndpoint,
    ): array {
        $groupedTrophies = $this->groupTrophiesByGroupId($flatTrophies);
        if ($groupedTrophies === []) {
            $groupedTrophies = $this->groupNestedTrophiesFromGroups($nestedGroupsFromTrophiesPayload);
        }

        return $this->buildTrophyGroups($rawGroupsFromGroupsEndpoint, $groupedTrophies);
    }

    /**
     * @param mixed $rawGroups
     * @param array<int, array{trophyGroupId: string, trophyGroupName: string, trophyGroupDetail: string, trophyGroupIconUrl: string, trophies: array<int, array<string, mixed>>}> $groupedTrophies
     * @return array<int, array{trophyGroupId: string, trophyGroupName: string, trophyGroupDetail: string, trophyGroupIconUrl: string, trophies: array<int, array<string, mixed>>}>
     */
    public function buildTrophyGroups(mixed $rawGroups, array $groupedTrophies): array
    {
        $trophiesByGroupId = [];
        foreach ($groupedTrophies as $groupedTrophy) {
            $groupId = $groupedTrophy['trophyGroupId'] ?? '';
            if (!is_string($groupId) || $groupId === '') {
                continue;
            }

            $trophiesByGroupId[$groupId] = $groupedTrophy['trophies'] ?? [];
        }

        if (!is_array($rawGroups)) {
            return $groupedTrophies;
        }

        $groups = [];

        foreach ($rawGroups as $rawGroup) {
            if (!is_array($rawGroup)) {
                continue;
            }

            $groupId = (string) ($rawGroup['trophyGroupId'] ?? '');
            if ($groupId === '') {
                continue;
            }

            $groups[] = [
                'trophyGroupId' => $groupId,
                'trophyGroupName' => (string) ($rawGroup['trophyGroupName'] ?? ''),
                'trophyGroupDetail' => (string) ($rawGroup['trophyGroupDetail'] ?? ''),
                'trophyGroupIconUrl' => (string) ($rawGroup['trophyGroupIconUrl'] ?? ''),
                'trophies' => is_array($trophiesByGroupId[$groupId] ?? null) ? $trophiesByGroupId[$groupId] : [],
            ];
        }

        if ($groups === []) {
            return $groupedTrophies;
        }

        return $groups;
    }

    /**
     * @return array<int, array{trophyGroupId: string, trophyGroupName: string, trophyGroupDetail: string, trophyGroupIconUrl: string, trophies: array<int, array<string, mixed>>}>
     */
    public function groupTrophiesByGroupId(mixed $rawTrophies): array
    {
        if (!is_array($rawTrophies)) {
            return [];
        }

        $groups = [];

        foreach ($rawTrophies as $rawTrophy) {
            if (!is_array($rawTrophy)) {
                continue;
            }

            $groupId = (string) ($rawTrophy['trophyGroupId'] ?? '');
            if ($groupId === '') {
                continue;
            }

            if (!isset($groups[$groupId])) {
                $groups[$groupId] = [
                    'trophyGroupId' => $groupId,
                    'trophyGroupName' => (string) ($rawTrophy['trophyGroupName'] ?? ''),
                    'trophyGroupDetail' => (string) ($rawTrophy['trophyGroupDetail'] ?? ''),
                    'trophyGroupIconUrl' => (string) ($rawTrophy['trophyGroupIconUrl'] ?? ''),
                    'trophies' => [],
                ];
            }

            $groups[$groupId]['trophies'][] = $rawTrophy;
        }

        return array_values($groups);
    }

    /**
     * @return array<int, array{trophyGroupId: string, trophyGroupName: string, trophyGroupDetail: string, trophyGroupIconUrl: string, trophies: array<int, array<string, mixed>>}>
     */
    public function groupNestedTrophiesFromGroups(mixed $rawGroups): array
    {
        if (!is_array($rawGroups)) {
            return [];
        }

        $groups = [];

        foreach ($rawGroups as $rawGroup) {
            if (!is_array($rawGroup)) {
                continue;
            }

            $groupId = (string) ($rawGroup['trophyGroupId'] ?? '');
            if ($groupId === '') {
                continue;
            }

            $trophies = $rawGroup['trophies'] ?? null;
            if (!is_array($trophies)) {
                $trophies = [];
            }

            $groups[] = [
                'trophyGroupId' => $groupId,
                'trophyGroupName' => (string) ($rawGroup['trophyGroupName'] ?? ''),
                'trophyGroupDetail' => (string) ($rawGroup['trophyGroupDetail'] ?? ''),
                'trophyGroupIconUrl' => (string) ($rawGroup['trophyGroupIconUrl'] ?? ''),
                'trophies' => $trophies,
            ];
        }

        return $groups;
    }
}
