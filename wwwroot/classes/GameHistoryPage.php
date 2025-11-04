<?php

declare(strict_types=1);

require_once __DIR__ . '/GameService.php';
require_once __DIR__ . '/GameHistoryService.php';
require_once __DIR__ . '/GameHeaderService.php';
require_once __DIR__ . '/GameNotFoundException.php';
require_once __DIR__ . '/Game/GameDetails.php';
require_once __DIR__ . '/Game/GameHeaderData.php';
require_once __DIR__ . '/PageMetaData.php';
require_once __DIR__ . '/Utility.php';

final class GameHistoryPage
{
    private GameService $gameService;

    private GameHistoryService $historyService;

    private GameHeaderService $gameHeaderService;

    private Utility $utility;

    private GameDetails $game;

    private GameHeaderData $headerData;

    /**
     * @var array<int, array{
     *     historyId: int,
     *     discoveredAt: DateTimeImmutable,
     *     title: ?array{detail: ?string, icon_url: ?string, set_version: ?string},
     *     titleHighlights: array{detail: bool, icon_url: bool, set_version: bool},
     *     hasTitleChanges: bool,
     *     groups: array<int, array{
     *         group_id: string,
     *         name: ?string,
     *         detail: ?string,
     *         icon_url: ?string,
     *         changedFields: array{name: bool, detail: bool, icon_url: bool},
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
     *         isNewRow: bool
     *     }>
     * }>|null
     */
    private ?array $historyEntries = null;

    public function __construct(
        GameService $gameService,
        GameHistoryService $historyService,
        GameHeaderService $gameHeaderService,
        Utility $utility,
        int $gameId
    ) {
        $this->gameService = $gameService;
        $this->historyService = $historyService;
        $this->gameHeaderService = $gameHeaderService;
        $this->utility = $utility;

        $this->game = $this->loadGame($gameId);
        $this->headerData = $this->gameHeaderService->buildHeaderData($this->game);
    }

    private function loadGame(int $gameId): GameDetails
    {
        $game = $this->gameService->getGame($gameId);

        if ($game === null) {
            throw new GameNotFoundException('Game not found: ' . $gameId);
        }

        return $game;
    }

    public function getGame(): GameDetails
    {
        return $this->game;
    }

    public function getGameHeaderData(): GameHeaderData
    {
        return $this->headerData;
    }

    /**
     * @return array<int, array{
     *     historyId: int,
     *     discoveredAt: DateTimeImmutable,
     *     title: ?array{detail: ?string, icon_url: ?string, set_version: ?string},
     *     titleHighlights: array{detail: bool, icon_url: bool, set_version: bool},
     *     titleDiffs?: array{detail: ?string, icon_url: ?string, set_version: ?string},
     *     titlePrevious?: array{detail: ?string, icon_url: ?string, set_version: ?string},
     *     hasTitleChanges: bool,
     *     groups: array<int, array{
     *         group_id: string,
     *         name: ?string,
     *         detail: ?string,
     *         icon_url: ?string,
     *         changedFields: array{name: bool, detail: bool, icon_url: bool},
     *         diffs?: array{name: ?string, detail: ?string, icon_url: ?string},
     *         previousValues?: array{name: ?string, detail: ?string, icon_url: ?string},
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
     *         diffs?: array{name: ?string, detail: ?string, icon_url: ?string, progress_target_value: ?string},
     *         previousValues?: array{name: ?string, detail: ?string, icon_url: ?string, progress_target_value: ?int},
     *         isNewRow: bool
     *     }>
     * }>
     */
    public function getHistoryEntries(): array
    {
        if ($this->historyEntries === null) {
            $history = $this->historyService->getHistoryForGame($this->game->getId());
            $this->historyEntries = $this->filterHistoryEntries($history);
        }

        return $this->historyEntries;
    }

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
     *     titleDiffs?: array{detail: ?string, icon_url: ?string, set_version: ?string},
     *     titlePrevious?: array{detail: ?string, icon_url: ?string, set_version: ?string},
     *     hasTitleChanges: bool,
     *     groups: array<int, array{
     *         group_id: string,
     *         name: ?string,
     *         detail: ?string,
     *         icon_url: ?string,
     *         changedFields: array{name: bool, detail: bool, icon_url: bool},
     *         diffs?: array{name: ?string, detail: ?string, icon_url: ?string},
     *         previousValues?: array{name: ?string, detail: ?string, icon_url: ?string},
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
     *         diffs?: array{name: ?string, detail: ?string, icon_url: ?string, progress_target_value: ?string},
     *         previousValues?: array{name: ?string, detail: ?string, icon_url: ?string, progress_target_value: ?int},
     *         isNewRow: bool
     *     }>
     * }>
     */
    private function filterHistoryEntries(array $entries): array
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
            $titlePreviousForDiff = $previousTitle;
            $titleDiffs = [
                'detail' => null,
                'icon_url' => null,
                'set_version' => null,
            ];

            if ($titleChange !== null) {
                $titleHighlights['detail'] = $this->isNewNonEmptyString($titleChange['detail'] ?? null, $previousTitle['detail']);
                $titleHighlights['icon_url'] = $this->isNewNonEmptyString($titleChange['icon_url'] ?? null, $previousTitle['icon_url']);
                $titleHighlights['set_version'] = $this->isNewNonEmptyString($titleChange['set_version'] ?? null, $previousTitle['set_version']);

                $hasTitleChanges = in_array(true, $titleHighlights, true);

                if ($titleHighlights['detail']) {
                    $titleDiffs['detail'] = $this->createUnifiedDiff($previousTitle['detail'], $titleChange['detail'] ?? null);
                }

                if ($titleHighlights['icon_url']) {
                    $titleDiffs['icon_url'] = $this->createUnifiedDiff($previousTitle['icon_url'], $titleChange['icon_url'] ?? null);
                }

                if ($titleHighlights['set_version']) {
                    $titleDiffs['set_version'] = $this->createUnifiedDiff($previousTitle['set_version'], $titleChange['set_version'] ?? null);
                }
            }

            $filteredGroups = [];
            foreach ($entry['groups'] as $groupChange) {
                $groupId = $groupChange['group_id'];
                $previousGroup = $previousGroups[$groupId] ?? ['name' => null, 'detail' => null, 'icon_url' => null];
                $groupPreviousForDiff = $previousGroup;

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
                    $groupChange['changedFields'] = $changedFields;
                    $groupChange['previousValues'] = $groupPreviousForDiff;
                    $groupChange['diffs'] = [
                        'name' => $changedFields['name'] ? $this->createUnifiedDiff($groupPreviousForDiff['name'], $groupChange['name'] ?? null) : null,
                        'detail' => $changedFields['detail'] ? $this->createUnifiedDiff($groupPreviousForDiff['detail'], $groupChange['detail'] ?? null) : null,
                        'icon_url' => $changedFields['icon_url'] ? $this->createUnifiedDiff($groupPreviousForDiff['icon_url'], $groupChange['icon_url'] ?? null) : null,
                    ];
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
                $trophyPreviousForDiff = $previousTrophy;

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
                    $trophyChange['changedFields'] = $changedFields;
                    $trophyChange['previousValues'] = $trophyPreviousForDiff;
                    $trophyChange['diffs'] = [
                        'name' => $changedFields['name'] ? $this->createUnifiedDiff($trophyPreviousForDiff['name'], $trophyChange['name'] ?? null) : null,
                        'detail' => $changedFields['detail'] ? $this->createUnifiedDiff($trophyPreviousForDiff['detail'], $trophyChange['detail'] ?? null) : null,
                        'icon_url' => $changedFields['icon_url'] ? $this->createUnifiedDiff($trophyPreviousForDiff['icon_url'], $trophyChange['icon_url'] ?? null) : null,
                        'progress_target_value' => $changedFields['progress_target_value']
                            ? $this->createUnifiedDiff(
                                $trophyPreviousForDiff['progress_target_value'] === null ? null : (string) $trophyPreviousForDiff['progress_target_value'],
                                $trophyChange['progress_target_value'] === null ? null : (string) $trophyChange['progress_target_value']
                            )
                            : null,
                    ];
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
                $entry['hasTitleChanges'] = $hasTitleChanges;
                if ($hasTitleChanges) {
                    $entry['titleDiffs'] = $titleDiffs;
                    $entry['titlePrevious'] = $titlePreviousForDiff;
                }
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

    private function createUnifiedDiff(?string $previous, ?string $current): ?string
    {
        $previousString = $previous ?? '';
        $currentString = $current ?? '';

        if ($previousString === $currentString) {
            return null;
        }

        $previousLines = $this->splitIntoLines($previousString);
        $currentLines = $this->splitIntoLines($currentString);

        $diffBody = $this->calculateDiffBody($previousLines, $currentLines);

        if ($diffBody === []) {
            return null;
        }

        $diffLines = ['--- Previous', '+++ Current', '@@'];

        foreach ($diffBody as $diffLine) {
            [$prefix, $line] = $diffLine;
            $diffLines[] = $prefix . $line;
        }

        return implode("\n", $diffLines);
    }

    /**
     * @param array<int, string> $previousLines
     * @param array<int, string> $currentLines
     * @return array<int, array{0: string, 1: string}>
     */
    private function calculateDiffBody(array $previousLines, array $currentLines): array
    {
        $diffBody = $this->buildFullDiff($previousLines, $currentLines);

        $start = 0;
        $end = count($diffBody);

        while ($start < $end && $diffBody[$start][0] === ' ') {
            $start++;
        }

        while ($end > $start && $diffBody[$end - 1][0] === ' ') {
            $end--;
        }

        return array_slice($diffBody, $start, $end - $start);
    }

    /**
     * @param array<int, string> $previousLines
     * @param array<int, string> $currentLines
     * @return array<int, array{0: string, 1: string}>
     */
    private function buildFullDiff(array $previousLines, array $currentLines): array
    {
        $previousCount = count($previousLines);
        $currentCount = count($currentLines);

        $lengthMatrix = [];

        for ($i = 0; $i <= $previousCount; $i++) {
            $lengthMatrix[$i] = array_fill(0, $currentCount + 1, 0);
        }

        for ($i = $previousCount - 1; $i >= 0; $i--) {
            for ($j = $currentCount - 1; $j >= 0; $j--) {
                if ($previousLines[$i] === $currentLines[$j]) {
                    $lengthMatrix[$i][$j] = $lengthMatrix[$i + 1][$j + 1] + 1;
                } else {
                    $lengthMatrix[$i][$j] = max($lengthMatrix[$i + 1][$j], $lengthMatrix[$i][$j + 1]);
                }
            }
        }

        $diffBody = [];
        $i = 0;
        $j = 0;

        while ($i < $previousCount && $j < $currentCount) {
            if ($previousLines[$i] === $currentLines[$j]) {
                $diffBody[] = [' ', $previousLines[$i]];
                $i++;
                $j++;
                continue;
            }

            if ($lengthMatrix[$i + 1][$j] >= $lengthMatrix[$i][$j + 1]) {
                $diffBody[] = ['-', $previousLines[$i]];
                $i++;
            } else {
                $diffBody[] = ['+', $currentLines[$j]];
                $j++;
            }
        }

        while ($i < $previousCount) {
            $diffBody[] = ['-', $previousLines[$i]];
            $i++;
        }

        while ($j < $currentCount) {
            $diffBody[] = ['+', $currentLines[$j]];
            $j++;
        }

        return $diffBody;
    }

    /**
     * @return array<int, string>
     */
    private function splitIntoLines(string $value): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);

        if ($normalized === '') {
            return [];
        }

        return explode("\n", $normalized);
    }

    public function createMetaData(): PageMetaData
    {
        return (new PageMetaData())
            ->setTitle($this->game->getName() . ' Trophy Data History')
            ->setDescription('Version history and trophy data changes for ' . $this->game->getName())
            ->setImage('https://psn100.net/img/title/' . $this->game->getIconUrl())
            ->setUrl('https://psn100.net/game-history/' . $this->game->getId() . '-' . $this->getGameSlug());
    }

    public function getPageTitle(): string
    {
        return $this->game->getName() . ' Trophy Data History ~ PSN 100%';
    }

    public function getGameSlug(): string
    {
        return $this->utility->slugify($this->game->getName());
    }
}
