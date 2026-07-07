<?php

declare(strict_types=1);

final class TrophyGroupConflictResolver
{
    public function parseNumericGroupId(string $groupId): ?int
    {
        if (!ctype_digit($groupId)) {
            return null;
        }

        $trimmed = ltrim($groupId, '0');

        if ($trimmed === '') {
            return 0;
        }

        return (int) $trimmed;
    }

    public function formatGroupId(int $numericValue, string $originalGroupId): string
    {
        $length = max(strlen($originalGroupId), 3);

        return str_pad((string) $numericValue, $length, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, string> $existingGroupMappings
     */
    public function determinePreferredGroupOffset(array $existingGroupMappings): ?int
    {
        foreach ($existingGroupMappings as $parentGroupId) {
            $numericGroupId = $this->parseNumericGroupId($parentGroupId);

            if ($numericGroupId === null) {
                continue;
            }

            $block = intdiv($numericGroupId, 100);

            return $block * 100;
        }

        return null;
    }

    /**
     * @param array<string, bool> $existingGroupIds
     */
    public function determineGroupOffset(array $existingGroupIds): int
    {
        $maxBlock = -1;

        foreach ($existingGroupIds as $groupId => $_) {
            $groupId = (string) $groupId;

            $numericGroupId = $this->parseNumericGroupId($groupId);

            if ($numericGroupId === null) {
                continue;
            }

            $block = intdiv($numericGroupId, 100);

            if ($block > $maxBlock) {
                $maxBlock = $block;
            }
        }

        if ($maxBlock < 0) {
            $maxBlock = 0;
        }

        return ($maxBlock + 1) * 100;
    }

    /**
     * @param array<string, string[]> $childGroupTrophyNames
     * @param array<string, string[]> $parentGroupTrophyNames
     * @param array<string, bool> $usedParentGroups
     */
    public function findMatchingParentGroupId(
        string $childGroupId,
        array $childGroupTrophyNames,
        array $parentGroupTrophyNames,
        array $usedParentGroups
    ): ?string {
        $childNames = $childGroupTrophyNames[$childGroupId] ?? null;

        if ($childNames === null || $childNames === []) {
            return null;
        }

        foreach ($parentGroupTrophyNames as $parentGroupId => $parentNames) {
            $parentGroupIdString = (string) $parentGroupId;

            if ($parentGroupIdString === $childGroupId) {
                continue;
            }

            if (isset($usedParentGroups[$parentGroupIdString])) {
                continue;
            }

            if ($parentNames === $childNames) {
                return $parentGroupIdString;
            }
        }

        return null;
    }

    /**
     * @param array<string, bool> $existingGroupIds
     * @return array{groupId: string, preferredOffset: ?int, groupOffset: int}
     */
    public function allocateNonConflictingGroupId(
        int $numericGroupId,
        string $originalGroupId,
        array $existingGroupIds,
        ?int $preferredOffset,
        int $groupOffset
    ): array {
        $candidateOffset = $preferredOffset ?? $groupOffset;
        $newGroupId = $this->formatGroupId($numericGroupId + $candidateOffset, $originalGroupId);

        while (isset($existingGroupIds[$newGroupId])) {
            if ($preferredOffset !== null) {
                $preferredOffset = null;
            }

            $candidateOffset += 100;
            $newGroupId = $this->formatGroupId($numericGroupId + $candidateOffset, $originalGroupId);
        }

        return [
            'groupId' => $newGroupId,
            'preferredOffset' => $preferredOffset,
            'groupOffset' => $candidateOffset,
        ];
    }
}
