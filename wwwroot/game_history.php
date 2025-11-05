<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/GameHistoryPage.php';

if (!isset($gameId)) {
    header('Location: /game/', true, 303);
    die();
}

$gameService = new GameService($database);
$gameHistoryService = new GameHistoryService($database);
$gameHeaderService = new GameHeaderService($database);

try {
    $gameHistoryPage = new GameHistoryPage(
        $gameService,
        $gameHistoryService,
        $gameHeaderService,
        $utility,
        (int) $gameId
    );
} catch (GameNotFoundException $exception) {
    header('Location: /game/', true, 303);
    die();
}

$game = $gameHistoryPage->getGame();
$gameHeaderData = $gameHistoryPage->getGameHeaderData();
$historyEntries = $gameHistoryPage->getHistoryEntries();
$metaData = $gameHistoryPage->createMetaData();
$title = $gameHistoryPage->getPageTitle();

require_once 'header.php';
?>

<?php
/**
 * @param string|null $value
 */
function historyFormatText(?string $value, bool $isMultiline = false): string
{
    if ($value === null || $value === '') {
        return '<span class="history-diff__empty">&mdash;</span>';
    }

    $escaped = htmlentities($value, ENT_QUOTES, 'UTF-8');

    return $isMultiline ? nl2br($escaped) : $escaped;
}

function historyFormatNumber(?int $value): string
{
    if ($value === null) {
        return '<span class="history-diff__empty">&mdash;</span>';
    }

    return htmlentities((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * @return list<string>
 */
function historyTokenizeString(string $value): array
{
    $tokens = preg_split('/(\s+)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

    if ($tokens === false) {
        return [$value];
    }

    return $tokens;
}

/**
 * @param list<string> $previousTokens
 * @param list<string> $currentTokens
 * @return array{previous: list<array{value: string, state: string}>, current: list<array{value: string, state: string}>}
 */
function historyBuildTokenDiff(array $previousTokens, array $currentTokens): array
{
    $previousLength = count($previousTokens);
    $currentLength = count($currentTokens);

    $lcs = array_fill(0, $previousLength + 1, array_fill(0, $currentLength + 1, 0));

    for ($i = $previousLength - 1; $i >= 0; $i--) {
        for ($j = $currentLength - 1; $j >= 0; $j--) {
            if ($previousTokens[$i] === $currentTokens[$j]) {
                $lcs[$i][$j] = $lcs[$i + 1][$j + 1] + 1;
            } else {
                $lcs[$i][$j] = max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }
    }

    $previousDiff = [];
    $currentDiff = [];
    $i = 0;
    $j = 0;

    while ($i < $previousLength && $j < $currentLength) {
        if ($previousTokens[$i] === $currentTokens[$j]) {
            $previousDiff[] = ['value' => $previousTokens[$i], 'state' => 'equal'];
            $currentDiff[] = ['value' => $currentTokens[$j], 'state' => 'equal'];
            $i++;
            $j++;
            continue;
        }

        if ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
            $previousDiff[] = ['value' => $previousTokens[$i], 'state' => 'removed'];
            $i++;
            continue;
        }

        $currentDiff[] = ['value' => $currentTokens[$j], 'state' => 'added'];
        $j++;
    }

    while ($i < $previousLength) {
        $previousDiff[] = ['value' => $previousTokens[$i], 'state' => 'removed'];
        $i++;
    }

    while ($j < $currentLength) {
        $currentDiff[] = ['value' => $currentTokens[$j], 'state' => 'added'];
        $j++;
    }

    return ['previous' => $previousDiff, 'current' => $currentDiff];
}

/**
 * @param list<array{value: string, state: string}> $tokens
 */
function historyRenderHighlightedTokens(array $tokens, bool $isMultiline, string $state): string
{
    $html = '';
    $highlightState = null;
    /** @var list<array{value: string, isWhitespace: bool}> $highlightTokens */
    $highlightTokens = [];

    $flushHighlight = static function () use (&$html, &$highlightState, &$highlightTokens, $state): void {
        if ($highlightState === null) {
            return;
        }

        $trailingWhitespace = [];

        while ($highlightTokens !== [] && $highlightTokens[array_key_last($highlightTokens)]['isWhitespace']) {
            $trailingWhitespace[] = array_pop($highlightTokens);
        }

        if ($highlightTokens !== []) {
            $modifierClass = $highlightState === 'removed' ? 'history-diff__token--removed' : 'history-diff__token--added';
            $spanContent = '';

            foreach ($highlightTokens as $token) {
                $spanContent .= $token['value'];
            }

            $html .= '<span class="history-diff__token ' . $modifierClass . ' history-diff__token--' . $state . '">' . $spanContent . '</span>';
        }

        if ($trailingWhitespace !== []) {
            for ($index = count($trailingWhitespace) - 1; $index >= 0; $index--) {
                $html .= $trailingWhitespace[$index]['value'];
            }
        }

        $highlightState = null;
        $highlightTokens = [];
    };

    $escapeTokenValue = static function (string $value) use ($isMultiline): string {
        $escaped = htmlentities($value, ENT_QUOTES, 'UTF-8');

        if ($isMultiline) {
            $escaped = str_replace(["\r\n", "\n", "\r"], '<br>', $escaped);
        }

        return $escaped;
    };

    foreach ($tokens as $token) {
        $isWhitespace = trim($token['value']) === '';
        $isHighlightedToken = ($token['state'] === 'removed' || $token['state'] === 'added') && !$isWhitespace;
        $escaped = $escapeTokenValue($token['value']);

        $shouldFlushHighlight = $highlightState !== null
            && ($isHighlightedToken ? $token['state'] !== $highlightState : !$isWhitespace);

        if ($shouldFlushHighlight) {
            $flushHighlight();
        }

        if ($isHighlightedToken) {
            if ($highlightState === null) {
                $highlightState = $token['state'];
            }

            $highlightTokens[] = ['value' => $escaped, 'isWhitespace' => $isWhitespace];
            continue;
        }

        if ($highlightState !== null && $isWhitespace) {
            $highlightTokens[] = ['value' => $escaped, 'isWhitespace' => true];
            continue;
        }

        $html .= $escaped;
    }

    if ($highlightState !== null) {
        $flushHighlight();
    }

    return $html;
}

/**
 * @return array{previous: string, current: string}
 */
function historyHighlightTextDiff(string $previousValue, string $currentValue, bool $isMultiline): array
{
    $previousTokens = historyTokenizeString($previousValue);
    $currentTokens = historyTokenizeString($currentValue);

    $diff = historyBuildTokenDiff($previousTokens, $currentTokens);

    $previousHtml = historyRenderHighlightedTokens($diff['previous'], $isMultiline, 'previous');
    $currentHtml = historyRenderHighlightedTokens($diff['current'], $isMultiline, 'current');

    return ['previous' => $previousHtml, 'current' => $currentHtml];
}

function historyRenderDiffBlocks(string $previousHtml, string $currentHtml): string
{
    return '<div class="history-diff">'
        . '<div class="history-diff__previous"><span class="visually-hidden">Previous value:</span>' . $previousHtml . '</div>'
        . '<div class="history-diff__current"><span class="visually-hidden">New value:</span>' . $currentHtml . '</div>'
        . '</div>';
}

/**
 * @param array{previous: mixed, current: mixed}|null $diff
 */
function historyRenderTextDiff(?array $diff, bool $isMultiline = false): string
{
    if ($diff === null) {
        return '';
    }

    $previousValue = is_string($diff['previous'] ?? null) ? $diff['previous'] : null;
    $currentValue = is_string($diff['current'] ?? null) ? $diff['current'] : null;

    if ($previousValue !== null && $currentValue !== null) {
        $highlighted = historyHighlightTextDiff($previousValue, $currentValue, $isMultiline);
        $previous = $highlighted['previous'];
        $current = $highlighted['current'];
    } else {
        $previous = historyFormatText($previousValue, $isMultiline);
        $current = historyFormatText($currentValue, $isMultiline);
    }

    return historyRenderDiffBlocks($previous, $current);
}

/**
 * @param array{previous: mixed, current: mixed}|null $diff
 */
function historyRenderNumberDiff(?array $diff): string
{
    if ($diff === null) {
        return '';
    }

    $previous = historyFormatNumber(isset($diff['previous']) ? (is_int($diff['previous']) ? $diff['previous'] : null) : null);
    $current = historyFormatNumber(isset($diff['current']) ? (is_int($diff['current']) ? $diff['current'] : null) : null);

    return historyRenderDiffBlocks($previous, $current);
}

function historyRenderSingleText(?string $value, bool $isMultiline = false): string
{
    return historyFormatText($value, $isMultiline);
}

function historyResolveIconPath(?string $iconUrl, GameDetails $game, string $type): ?string
{
    if ($iconUrl === null || $iconUrl === '') {
        return null;
    }

    if ($iconUrl === '.png') {
        $hasPs5Assets = str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2');

        if ($type === 'group' || $type === 'title') {
            return $hasPs5Assets ? '../missing-ps5-game-and-trophy.png' : '../missing-ps4-game.png';
        }

        if ($type === 'trophy') {
            return $hasPs5Assets ? '../missing-ps5-game-and-trophy.png' : '../missing-ps4-trophy.png';
        }
    }

    return $iconUrl;
}

function historyFormatIcon(?string $iconUrl, GameDetails $game, string $type, ?string $name, string $state): string
{
    $resolvedPath = historyResolveIconPath($iconUrl, $game, $type);

    if ($resolvedPath === null) {
        return '<div class="text-center"><span class="history-diff__empty">&mdash;</span></div>';
    }

    $borderClass = $state === 'previous' ? 'border-danger' : 'border-success';
    $objectFit = 'object-fit-scale';
    $directory = 'trophy';
    $height = 3.5;

    if ($type === 'group') {
        $objectFit = 'object-fit-cover';
        $directory = 'group';
    } elseif ($type === 'title') {
        $directory = 'title';
        $height = 5.5;
    }

    return '<div class="text-center">'
        . '<img class="' . $objectFit . ' border border-2 ' . $borderClass . ' rounded" style="height: ' . $height . 'rem;" src="/img/' . $directory . '/'
        . htmlentities($resolvedPath, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlentities($name ?? '', ENT_QUOTES, 'UTF-8') . '">'
        . '</div>';
}

/**
 * @param array{previous: mixed, current: mixed}|null $diff
 */
function historyRenderIconDiff(?array $diff, GameDetails $game, string $type, ?string $name): string
{
    if ($diff === null) {
        return '';
    }

    $previous = historyFormatIcon(is_string($diff['previous'] ?? null) ? $diff['previous'] : null, $game, $type, $name, 'previous');
    $current = historyFormatIcon(is_string($diff['current'] ?? null) ? $diff['current'] : null, $game, $type, $name, 'current');

    return historyRenderDiffBlocks($previous, $current);
}

function historyRenderSingleIcon(?string $iconUrl, GameDetails $game, string $type, ?string $name): string
{
    $resolvedPath = historyResolveIconPath($iconUrl, $game, $type);

    if ($resolvedPath === null) {
        return '<div class="text-center"><span class="history-diff__empty">&mdash;</span></div>';
    }

    $objectFit = 'object-fit-scale';
    $directory = 'trophy';
    $height = 3.5;

    if ($type === 'group') {
        $objectFit = 'object-fit-cover';
        $directory = 'group';
    } elseif ($type === 'title') {
        $directory = 'title';
        $height = 5.5;
    }

    return '<div class="text-center">'
        . '<img class="' . $objectFit . ' rounded" style="height: ' . $height . 'rem;" src="/img/' . $directory . '/'
        . htmlentities($resolvedPath, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlentities($name ?? '', ENT_QUOTES, 'UTF-8') . '">'
        . '</div>';
}
?>

<main class="container">
    <style>
        .history-diff {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .history-diff__previous,
        .history-diff__current {
            border-radius: var(--bs-border-radius);
            padding: 0.375rem 0.5rem;
        }

        .history-diff__previous {
            background-color: var(--bs-danger-bg-subtle);
            color: var(--bs-danger-text-emphasis);
        }

        .history-diff__current {
            background-color: var(--bs-success-bg-subtle);
            color: var(--bs-success-text-emphasis);
        }

        .history-diff__empty {
            display: inline-block;
            opacity: 0.75;
        }

        .history-diff__token {
            border-radius: var(--bs-border-radius-sm);
            box-decoration-break: clone;
            display: inline;
            margin: 0 -0.05rem;
            padding: 0.05rem 0.15rem;
        }

        .history-diff__token--previous.history-diff__token--removed {
            background-color: var(--bs-danger-border-subtle);
            color: var(--bs-danger-text-emphasis);
        }

        .history-diff__token--current.history-diff__token--added {
            background-color: var(--bs-success-border-subtle);
            color: var(--bs-success-text-emphasis);
        }
    </style>
    <?php require_once 'game_header.php'; ?>

    <div class="p-3">
        <div class="row">
            <div class="col-12 col-lg-3">
            </div>

            <div class="col-12 col-lg-6 mb-3 text-center">
                <div class="btn-group">
                    <a class="btn btn-outline-primary" href="/game/<?= $game->getId() . '-' . $utility->slugify($game->getName()); ?><?= isset($player) ? '/' . $player : ''; ?>">Trophies</a>
                    <a class="btn btn-outline-primary" href="/game-leaderboard/<?= $game->getId() . '-' . $utility->slugify($game->getName()); ?><?= isset($player) ? '/' . $player : ''; ?>">Leaderboard</a>
                    <a class="btn btn-outline-primary" href="/game-recent-players/<?= $game->getId() . '-' . $utility->slugify($game->getName()); ?><?= isset($player) ? '/' . $player : ''; ?>">Recent Players</a>
                    <a class="btn btn-primary active" href="/game-history/<?= $game->getId() . '-' . $utility->slugify($game->getName()); ?>">History</a>
                </div>
            </div>

            <div class="col-12 col-lg-3">
            </div>
        </div>
    </div>

    <div class="bg-body-tertiary p-3 rounded mb-3">
        <?php if ($historyEntries === []) { ?>
            <div class="alert alert-info mb-0">No trophy data history has been recorded for this game yet.</div>
        <?php } else { ?>
            <div class="vstack gap-3">
                <?php foreach ($historyEntries as $entry) { ?>
                    <div class="card">
                        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                            <div>
                                <?php
                                $titleChange = $entry['title'] ?? null;
                                $titleHighlights = $entry['titleHighlights'] ?? ['detail' => false, 'icon_url' => false, 'set_version' => false];
                                $hasTitleChanges = $entry['hasTitleChanges'] ?? false;
                                $setVersion = $titleChange['set_version'] ?? null;
                                $titleFieldDiffs = $entry['titleFieldDiffs'] ?? [];
                                ?>
                                <span class="fw-semibold">
                                    Version <?= htmlentities($setVersion ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($titleHighlights['set_version'] ?? false) { ?>
                                        <span class="badge text-bg-success ms-2">New</span>
                                    <?php } ?>
                                </span>
                            </div>
                            <?php $discoveredAt = $entry['discoveredAt']; ?>
                            <time class="text-body-secondary small js-localized-datetime" datetime="<?= htmlentities($discoveredAt->format(DATE_ATOM), ENT_QUOTES, 'UTF-8'); ?>">
                                <?= htmlentities($discoveredAt->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?> UTC
                            </time>
                        </div>
                        <div class="card-body">
                            <?php if ($titleChange !== null && ($titleHighlights['set_version'] ?? false)) { ?>
                                <div class="row mb-3">
                                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                                        <?= historyRenderTextDiff($titleFieldDiffs['set_version'] ?? null); ?>
                                    </div>
                                </div>
                            <?php } ?>

                            <?php if ($titleChange !== null && $hasTitleChanges && (($titleHighlights['detail'] ?? false) || ($titleHighlights['icon_url'] ?? false))) { ?>
                                <div class="row g-3 align-items-start mb-3">
                                    <?php if ($titleHighlights['icon_url'] ?? false) { ?>
                                        <div class="col-12 col-md-4 col-lg-3">
                                            <?= historyRenderIconDiff($titleFieldDiffs['icon_url'] ?? null, $game, 'title', $game->getName()); ?>
                                        </div>
                                    <?php } ?>
                                    <?php if ($titleHighlights['detail'] ?? false) { ?>
                                        <div class="col-12 <?= ($titleHighlights['icon_url'] ?? false) ? 'col-md-8 col-lg-9' : 'col-md-12'; ?>">
                                            <?= historyRenderTextDiff($titleFieldDiffs['detail'] ?? null, true); ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>

                            <?php $groupChanges = $entry['groups'] ?? []; ?>
                            <?php if ($groupChanges !== []) { ?>
                                <div class="mb-3">
                                    <h2 class="h5">Trophy Groups</h2>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Group</th>
                                                    <th scope="col">Name</th>
                                                    <th scope="col">Detail</th>
                                                    <th scope="col" class="text-center">Icon</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($groupChanges as $groupChange) { ?>
                                                    <?php
                                                    $groupChangedFields = $groupChange['changedFields'] ?? ['name' => false, 'detail' => false, 'icon_url' => false];
                                                    $groupFieldDiffs = $groupChange['fieldDiffs'] ?? [];
                                                    $groupIsNewRow = $groupChange['isNewRow'] ?? false;
                                                    ?>
                                                    <tr class="<?= $groupIsNewRow ? 'table-success' : ''; ?>">
                                                        <td>
                                                            <span class="badge text-bg-secondary"><?= htmlentities($groupChange['group_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if ($groupIsNewRow) { ?>
                                                                <?= historyRenderSingleText($groupChange['name'] ?? null); ?>
                                                            <?php } elseif ($groupChangedFields['name'] ?? false) { ?>
                                                                <?= historyRenderTextDiff($groupFieldDiffs['name'] ?? null); ?>
                                                            <?php } else { ?>
                                                                <?= historyRenderSingleText($groupChange['name'] ?? null); ?>
                                                            <?php } ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($groupIsNewRow) { ?>
                                                                <?= historyRenderSingleText($groupChange['detail'] ?? null, true); ?>
                                                            <?php } elseif ($groupChangedFields['detail'] ?? false) { ?>
                                                                <?= historyRenderTextDiff($groupFieldDiffs['detail'] ?? null, true); ?>
                                                            <?php } else { ?>
                                                                <?= historyRenderSingleText($groupChange['detail'] ?? null, true); ?>
                                                            <?php } ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($groupIsNewRow) { ?>
                                                                <?= historyRenderSingleIcon($groupChange['icon_url'] ?? null, $game, 'group', $groupChange['name'] ?? ''); ?>
                                                            <?php } elseif ($groupChangedFields['icon_url'] ?? false) { ?>
                                                                <?= historyRenderIconDiff($groupFieldDiffs['icon_url'] ?? null, $game, 'group', $groupChange['name'] ?? ''); ?>
                                                            <?php } else { ?>
                                                                <?= historyRenderSingleIcon($groupChange['icon_url'] ?? null, $game, 'group', $groupChange['name'] ?? ''); ?>
                                                            <?php } ?>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php } ?>

                            <?php $trophyChanges = $entry['trophies'] ?? []; ?>
                            <?php if ($trophyChanges !== []) { ?>
                                <div>
                                    <h2 class="h5">Trophies</h2>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Group</th>
                                                    <th scope="col">#</th>
                                                    <th scope="col">Name</th>
                                                    <th scope="col">Detail</th>
                                                    <th scope="col">Target</th>
                                                    <th scope="col" class="text-center">Icon</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($trophyChanges as $trophyChange) { ?>
                                                    <?php
                                                    $trophyChangedFields = $trophyChange['changedFields'] ?? ['name' => false, 'detail' => false, 'icon_url' => false, 'progress_target_value' => false];
                                                    $trophyFieldDiffs = $trophyChange['fieldDiffs'] ?? [];
                                                    $trophyIsNewRow = $trophyChange['isNewRow'] ?? false;
                                                    ?>
                                                    <tr class="<?= $trophyIsNewRow ? 'table-success' : ''; ?>">
                                                        <td class="<?= ($trophyChange['isNewRow'] ?? false) ? 'table-success' : ''; ?>">
                                                            <span class="badge text-bg-secondary"><?= htmlentities($trophyChange['group_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </td>
                                                        <td class="<?= ($trophyChange['isNewRow'] ?? false) ? 'table-success' : ''; ?>">
                                                            <?php if ($trophyChange['is_unobtainable'] ?? false) { ?>
                                                                <span class="badge text-bg-warning" title="This trophy is unobtainable and not accounted for on any leaderboard."><?= (int) $trophyChange['order_id']; ?></span>
                                                            <?php } else { ?>
                                                                <?= (int) $trophyChange['order_id']; ?>
                                                            <?php } ?>
                                                        </td>
                                                        <td class="<?= (($trophyChange['isNewRow'] ?? false) || ($trophyChangedFields['name'] ?? false)) ? 'table-success' : ''; ?>">
                                                            <?php if ($trophyIsNewRow) { ?>
                                                                <?= historyRenderSingleText($trophyChange['name'] ?? null); ?>
                                                            <?php } elseif ($trophyChangedFields['name'] ?? false) { ?>
                                                                <?= historyRenderTextDiff($trophyFieldDiffs['name'] ?? null); ?>
                                                            <?php } else { ?>
                                                                <?= historyRenderSingleText($trophyChange['name'] ?? null); ?>
                                                            <?php } ?>
                                                        </td>
                                                        <td class="<?= (($trophyChange['isNewRow'] ?? false) || ($trophyChangedFields['detail'] ?? false)) ? 'table-success' : ''; ?>">
                                                            <?php if ($trophyIsNewRow) { ?>
                                                                <?= historyRenderSingleText($trophyChange['detail'] ?? null, true); ?>
                                                            <?php } elseif ($trophyChangedFields['detail'] ?? false) { ?>
                                                                <?= historyRenderTextDiff($trophyFieldDiffs['detail'] ?? null, true); ?>
                                                            <?php } else { ?>
                                                                <?= historyRenderSingleText($trophyChange['detail'] ?? null, true); ?>
                                                            <?php } ?>
                                                        </td>
                                                        <td class="<?= (($trophyChange['isNewRow'] ?? false) || ($trophyChangedFields['progress_target_value'] ?? false)) ? 'table-success' : ''; ?>">
                                                            <?php if ($trophyIsNewRow) { ?>
                                                                <?= historyFormatNumber($trophyChange['progress_target_value'] ?? null); ?>
                                                            <?php } elseif ($trophyChangedFields['progress_target_value'] ?? false) { ?>
                                                                <?= historyRenderNumberDiff($trophyFieldDiffs['progress_target_value'] ?? null); ?>
                                                            <?php } else { ?>
                                                                <?= historyFormatNumber($trophyChange['progress_target_value'] ?? null); ?>
                                                            <?php } ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($trophyIsNewRow) { ?>
                                                                <?= historyRenderSingleIcon($trophyChange['icon_url'] ?? null, $game, 'trophy', $trophyChange['name'] ?? ''); ?>
                                                            <?php } elseif ($trophyChangedFields['icon_url'] ?? false) { ?>
                                                                <?= historyRenderIconDiff($trophyFieldDiffs['icon_url'] ?? null, $game, 'trophy', $trophyChange['name'] ?? ''); ?>
                                                            <?php } else { ?>
                                                                <?= historyRenderSingleIcon($trophyChange['icon_url'] ?? null, $game, 'trophy', $trophyChange['name'] ?? ''); ?>
                                                            <?php } ?>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Intl === 'undefined' || typeof Intl.DateTimeFormat !== 'function') {
                return;
            }

            const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            const pad = (value) => value.toString().padStart(2, '0');

            document.querySelectorAll('.js-localized-datetime').forEach((timeElement) => {
                const isoString = timeElement.getAttribute('datetime');

                if (!isoString) {
                    return;
                }

                const date = new Date(isoString);

                if (Number.isNaN(date.getTime())) {
                    return;
                }

                const formattedDate = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
                const formattedTime = `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;

                timeElement.textContent = `${formattedDate} ${formattedTime}${timeZone ? ` ${timeZone}` : ''}`;
                timeElement.setAttribute('data-timezone', timeZone);
            });
        });
    </script>
</main>

<?php require_once 'footer.php'; ?>
