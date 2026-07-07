<?php

declare(strict_types=1);

/**
 * Filters raw game history rows down to entries with meaningful changes and
 * annotates each row with field-level diff metadata for rendering.
 */
final class GameHistoryChangeFilter
{
    /**
     * @param array<int, array{
     *     historyId: int,
     *     discoveredAt: DateTimeImmutable,
     *     title: ?array{detail: ?string, icon_url: ?string, set_version: ?string},
     *     groups: array<int, array{group_id: string, name: ?string, detail: ?string, icon_url: ?string}>,
     *     trophies: array<int, array{group_id: string, order_id: int, name: ?string, detail: ?string, icon_url: ?string, progress_target_value: ?int, is_unobtainable: bool}>
     * }> $entries
     * @return array<int, array{
     *     historyId: int,
     *     discoveredAt: DateTimeImmutable,
     *     title: ?array{detail: ?string, icon_url: ?string, set_version: ?string},
     *     titleHighlights: array{detail: bool, icon_url: bool, set_version: bool},
     *     titleFieldDiffs?: array<string, array{previous: mixed, current: mixed}>,
     *     hasTitleChanges: bool,
     *     isTitleNewRow: bool,
     *     groups: array<int, array{
     *         group_id: string,
     *         name: ?string,
     *         detail: ?string,
     *         icon_url: ?string,
     *         changedFields: array{name: bool, detail: bool, icon_url: bool},
     *         fieldDiffs?: array<string, array{previous: mixed, current: mixed}>,
     *         isNewRow: bool
     *     }>,
     *     trophies: array<int, array{
     *         group_id: string,
     *         order_id: int,
     *         name: ?string,
     *         detail: ?string,
     *         icon_url: ?string,
     *         progress_target_value: ?int,
     *         is_unobtainable: bool,
     *         changedFields: array{name: bool, detail: bool, icon_url: bool, progress_target_value: bool},
     *         fieldDiffs?: array<string, array{previous: mixed, current: mixed}>,
     *         isNewRow: bool
     *     }>
     * }>
     */
    public function filter(array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        $previousTitle = [
            'detail' => null,
            'icon_url' => null,
            'set_version' => null,
        ];

        /** @var array<string, array{name: ?string, detail: ?string, icon_url: ?string}> $previousGroups */
        $previousGroups = [];

        /** @var array<string, array{name: ?string, detail: ?string, icon_url: ?string, progress_target_value: ?int}> $previousTrophies */
        $previousTrophies = [];

        $processedEntries = [];

        $chronologicalEntries = array_reverse($entries);

        foreach ($chronologicalEntries as $entry) {
            $titleChange = $entry['title'];
            $titleHighlights = [
                'detail' => false,
                'icon_url' => false,
                'set_version' => false,
            ];
            $hasTitleChanges = false;

            $titleFieldDiffs = [];
            $isTitleNewRow = $titleChange !== null
                && $previousTitle['detail'] === null
                && $previousTitle['icon_url'] === null
                && $previousTitle['set_version'] === null;

            if ($titleChange !== null) {
                $titleHighlights['detail'] = $this->isNewNonEmptyString($titleChange['detail'] ?? null, $previousTitle['detail']);
                $titleHighlights['icon_url'] = $this->isNewNonEmptyString($titleChange['icon_url'] ?? null, $previousTitle['icon_url']);
                $titleHighlights['set_version'] = $this->isNewNonEmptyString($titleChange['set_version'] ?? null, $previousTitle['set_version']);

                $hasTitleChanges = in_array(true, $titleHighlights, true);

                foreach (['detail', 'icon_url', 'set_version'] as $field) {
                    if ($titleHighlights[$field]) {
                        $currentValue = $this->normalizeString($titleChange[$field] ?? null);
                        $titleFieldDiffs[$field] = [
                            'previous' => $previousTitle[$field] ?? null,
                            'current' => $currentValue,
                        ];
                    }
                }
            }

            $filteredGroups = [];
            foreach ($entry['groups'] as $groupChange) {
                $groupId = $groupChange['group_id'];
                $previousGroup = $previousGroups[$groupId] ?? ['name' => null, 'detail' => null, 'icon_url' => null];

                $changedFields = [
                    'name' => $this->isNewNonEmptyString($groupChange['name'] ?? null, $previousGroup['name']),
                    'detail' => $this->isNewNonEmptyString($groupChange['detail'] ?? null, $previousGroup['detail']),
                    'icon_url' => $this->isNewNonEmptyString($groupChange['icon_url'] ?? null, $previousGroup['icon_url']),
                ];

                $isNewRow = !array_key_exists($groupId, $previousGroups);

                if ($isNewRow) {
                    foreach (array_keys($changedFields) as $field) {
                        if ($this->hasNonEmptyString($groupChange[$field] ?? null)) {
                            $changedFields[$field] = true;
                        }
                    }
                }

                $hasRowChanges = $isNewRow || in_array(true, $changedFields, true);

                if ($hasRowChanges) {
                    $fieldDiffs = [];

                    foreach ($changedFields as $field => $hasChanged) {
                        if ($hasChanged || $isNewRow) {
                            $fieldDiffs[$field] = [
                                'previous' => $previousGroup[$field] ?? null,
                                'current' => $this->normalizeString($groupChange[$field] ?? null),
                            ];
                        }
                    }

                    if ($fieldDiffs !== []) {
                        $groupChange['fieldDiffs'] = $fieldDiffs;
                    }

                    $groupChange['changedFields'] = $changedFields;
                    $groupChange['isNewRow'] = $isNewRow;
                    $filteredGroups[] = $groupChange;
                }

                $previousGroups[$groupId] = [
                    'name' => $this->normalizeString($groupChange['name'] ?? null),
                    'detail' => $this->normalizeString($groupChange['detail'] ?? null),
                    'icon_url' => $this->normalizeString($groupChange['icon_url'] ?? null),
                ];
            }

            $filteredTrophies = [];
            foreach ($entry['trophies'] as $trophyChange) {
                $trophyKey = $trophyChange['group_id'] . ':' . $trophyChange['order_id'];
                $previousTrophy = $previousTrophies[$trophyKey] ?? [
                    'name' => null,
                    'detail' => null,
                    'icon_url' => null,
                    'progress_target_value' => null,
                ];

                $changedFields = [
                    'name' => $this->isNewNonEmptyString($trophyChange['name'] ?? null, $previousTrophy['name']),
                    'detail' => $this->isNewNonEmptyString($trophyChange['detail'] ?? null, $previousTrophy['detail']),
                    'icon_url' => $this->isNewNonEmptyString($trophyChange['icon_url'] ?? null, $previousTrophy['icon_url']),
                    'progress_target_value' => $this->isNewInt($trophyChange['progress_target_value'] ?? null, $previousTrophy['progress_target_value']),
                ];

                $isNewRow = !array_key_exists($trophyKey, $previousTrophies);

                if ($isNewRow) {
                    foreach (array_keys($changedFields) as $field) {
                        if ($field === 'progress_target_value') {
                            if ($trophyChange['progress_target_value'] !== null) {
                                $changedFields[$field] = true;
                            }
                        } else {
                            if ($this->hasNonEmptyString($trophyChange[$field] ?? null)) {
                                $changedFields[$field] = true;
                            }
                        }
                    }
                }

                $hasRowChanges = $isNewRow || in_array(true, $changedFields, true);

                if ($hasRowChanges) {
                    $fieldDiffs = [];

                    foreach ($changedFields as $field => $hasChanged) {
                        if ($hasChanged || $isNewRow) {
                            if ($field === 'progress_target_value') {
                                $currentValue = $trophyChange['progress_target_value'] ?? null;
                                $previousValue = $previousTrophy['progress_target_value'] ?? null;
                            } else {
                                $currentValue = $this->normalizeString($trophyChange[$field] ?? null);
                                $previousValue = $previousTrophy[$field] ?? null;
                            }

                            $fieldDiffs[$field] = [
                                'previous' => $previousValue,
                                'current' => $currentValue,
                            ];
                        }
                    }

                    if ($fieldDiffs !== []) {
                        $trophyChange['fieldDiffs'] = $fieldDiffs;
                    }

                    $trophyChange['changedFields'] = $changedFields;
                    $trophyChange['isNewRow'] = $isNewRow;
                    $filteredTrophies[] = $trophyChange;
                }

                $previousTrophies[$trophyKey] = [
                    'name' => $this->normalizeString($trophyChange['name'] ?? null),
                    'detail' => $this->normalizeString($trophyChange['detail'] ?? null),
                    'icon_url' => $this->normalizeString($trophyChange['icon_url'] ?? null),
                    'progress_target_value' => $trophyChange['progress_target_value'] ?? null,
                ];
            }

            $entryHasChanges = $hasTitleChanges || $filteredGroups !== [] || $filteredTrophies !== [];

            if ($entryHasChanges) {
                $entry['titleHighlights'] = $titleHighlights;
                if ($titleFieldDiffs !== []) {
                    $entry['titleFieldDiffs'] = $titleFieldDiffs;
                }
                $entry['hasTitleChanges'] = $hasTitleChanges;
                $entry['isTitleNewRow'] = $isTitleNewRow;
                $entry['groups'] = $filteredGroups;
                $entry['trophies'] = $filteredTrophies;

                $processedEntries[] = $entry;
            }

            if ($titleChange !== null) {
                $previousTitle = [
                    'detail' => $this->normalizeString($titleChange['detail'] ?? null),
                    'icon_url' => $this->normalizeString($titleChange['icon_url'] ?? null),
                    'set_version' => $this->normalizeString($titleChange['set_version'] ?? null),
                ];
            }
        }

        return array_reverse($processedEntries);
    }

    private function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value === '' ? null : $value;
    }

    private function hasNonEmptyString(?string $value): bool
    {
        return $this->normalizeString($value) !== null;
    }

    private function isNewNonEmptyString(?string $value, ?string $previous): bool
    {
        $normalized = $this->normalizeString($value);

        if ($normalized === null) {
            return false;
        }

        return $normalized !== $previous;
    }

    private function isNewInt(?int $value, ?int $previous): bool
    {
        if ($value === null) {
            return false;
        }

        return $value !== $previous;
    }
}
