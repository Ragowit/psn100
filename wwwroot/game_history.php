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

$renderDiffTextLines = static function (string $label, ?string $previous, ?string $current, bool $multiline = false): string {
    if ($previous === null && $current === null) {
        return '';
    }

    $escapeValue = static function (?string $value) use ($multiline): string {
        if ($value === null) {
            return '';
        }

        $escaped = htmlentities($value, ENT_QUOTES, 'UTF-8');

        return $multiline ? nl2br($escaped) : $escaped;
    };

    $labelHtml = htmlentities($label, ENT_QUOTES, 'UTF-8');

    $html = '';

    if ($previous !== null) {
        $html .= '<div class="diff-line diff-removed"><span class="diff-symbol">-</span><span class="diff-label">' . $labelHtml . '</span><span class="diff-value">' . $escapeValue($previous) . '</span></div>';
    }

    if ($current !== null) {
        $html .= '<div class="diff-line diff-added"><span class="diff-symbol">+</span><span class="diff-label">' . $labelHtml . '</span><span class="diff-value">' . $escapeValue($current) . '</span></div>';
    }

    return $html;
};

$renderDiffIconLines = static function (
    string $label,
    ?string $previous,
    ?string $current,
    callable $pathResolver,
    string $basePath,
    string $altText,
    string $imageClass
): string {
    if ($previous === null && $current === null) {
        return '';
    }

    $labelHtml = htmlentities($label, ENT_QUOTES, 'UTF-8');
    $altHtml = htmlentities($altText !== '' ? $altText : 'Icon', ENT_QUOTES, 'UTF-8');
    $imageClassHtml = htmlentities($imageClass, ENT_QUOTES, 'UTF-8');

    $buildImage = static function (?string $iconValue) use ($pathResolver, $basePath, $altHtml, $imageClassHtml): ?string {
        if ($iconValue === null) {
            return null;
        }

        $resolvedPath = $pathResolver($iconValue);

        if ($resolvedPath === null || $resolvedPath === '') {
            return null;
        }

        $src = htmlentities($basePath . $resolvedPath, ENT_QUOTES, 'UTF-8');

        return '<img class="diff-image ' . $imageClassHtml . '" src="' . $src . '" alt="' . $altHtml . '">';
    };

    $html = '';

    $previousImage = $buildImage($previous);
    if ($previousImage !== null) {
        $html .= '<div class="diff-line diff-removed"><span class="diff-symbol">-</span><span class="diff-label">' . $labelHtml . '</span><span class="diff-value">' . $previousImage . '</span></div>';
    }

    $currentImage = $buildImage($current);
    if ($currentImage !== null) {
        $html .= '<div class="diff-line diff-added"><span class="diff-symbol">+</span><span class="diff-label">' . $labelHtml . '</span><span class="diff-value">' . $currentImage . '</span></div>';
    }

    return $html;
};

$resolveTitleIconPath = static function (?string $iconUrl) use ($game): ?string {
    if ($iconUrl === null || $iconUrl === '') {
        return null;
    }

    if ($iconUrl === '.png') {
        return (str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2'))
            ? '../missing-ps5-game-and-trophy.png'
            : '../missing-ps4-game.png';
    }

    return $iconUrl;
};

$resolveGroupIconPath = static function (?string $iconUrl) use ($game): ?string {
    if ($iconUrl === null || $iconUrl === '') {
        return null;
    }

    if ($iconUrl === '.png') {
        return (str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2'))
            ? '../missing-ps5-game-and-trophy.png'
            : '../missing-ps4-game.png';
    }

    return $iconUrl;
};

$resolveTrophyIconPath = static function (?string $iconUrl) use ($game): ?string {
    if ($iconUrl === null || $iconUrl === '') {
        return null;
    }

    if ($iconUrl === '.png') {
        return (str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2'))
            ? '../missing-ps5-game-and-trophy.png'
            : '../missing-ps4-trophy.png';
    }

    return $iconUrl;
};
?>

<main class="container">
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

    <style>
        .diff-block {
            font-family: var(--bs-font-monospace);
            border: 1px solid var(--bs-border-color);
            border-radius: var(--bs-border-radius-lg);
            overflow: hidden;
        }

        .diff-block + .diff-block {
            margin-top: 1rem;
        }

        .diff-line {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            padding: 0.5rem 0.75rem;
        }

        .diff-line + .diff-line {
            border-top: 1px solid var(--bs-border-color);
        }

        .diff-header {
            background-color: var(--bs-secondary-bg);
            color: var(--bs-secondary-color);
        }

        .diff-symbol {
            font-weight: 700;
            width: 1.5rem;
            text-align: center;
        }

        .diff-label {
            font-weight: 600;
            min-width: 4.5rem;
        }

        .diff-label::after {
            content: ':';
            margin-left: 0.25rem;
        }

        .diff-value {
            flex: 1 1 auto;
            white-space: normal;
            word-break: break-word;
        }

        .diff-added {
            background-color: var(--bs-success-bg-subtle);
            color: var(--bs-success-text-emphasis);
        }

        .diff-removed {
            background-color: var(--bs-danger-bg-subtle);
            color: var(--bs-danger-text-emphasis);
        }

        .diff-context {
            flex: 1 1 auto;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .diff-image {
            border-radius: var(--bs-border-radius);
            border: 1px solid var(--bs-border-color);
            background-color: var(--bs-body-bg);
        }

        .diff-image-lg {
            max-height: 5.5rem;
        }

        .diff-image-md {
            max-height: 3.5rem;
        }

        @media (max-width: 576px) {
            .diff-line {
                flex-direction: column;
                align-items: flex-start;
            }

            .diff-symbol {
                width: auto;
            }

            .diff-label {
                min-width: 0;
            }

            .diff-context {
                width: 100%;
            }
        }
    </style>

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
                            <?php
                            $previousTitle = $entry['previousTitle'] ?? ['detail' => null, 'icon_url' => null, 'set_version' => null];
                            $groupChanges = $entry['groups'] ?? [];
                            $trophyChanges = $entry['trophies'] ?? [];
                            ?>

                            <?php if ($titleChange !== null && $hasTitleChanges) { ?>
                                <div class="mb-3">
                                    <h2 class="h5">Title</h2>
                                    <div class="diff-block">
                                        <div class="diff-line diff-header">
                                            <span class="diff-symbol">@</span>
                                            <div class="diff-context">
                                                <span>Title metadata</span>
                                            </div>
                                        </div>
                                        <?php if ($titleHighlights['detail'] ?? false) { ?>
                                            <?= $renderDiffTextLines('Detail', $previousTitle['detail'] ?? null, $titleChange['detail'] ?? null, true); ?>
                                        <?php } ?>
                                        <?php if ($titleHighlights['icon_url'] ?? false) { ?>
                                            <?= $renderDiffIconLines('Icon', $previousTitle['icon_url'] ?? null, $titleChange['icon_url'] ?? null, $resolveTitleIconPath, '/img/title/', $game->getName(), 'diff-image-lg object-fit-scale'); ?>
                                        <?php } ?>
                                        <?php if ($titleHighlights['set_version'] ?? false) { ?>
                                            <?= $renderDiffTextLines('Version', $previousTitle['set_version'] ?? null, $titleChange['set_version'] ?? null); ?>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>

                            <?php if ($groupChanges !== []) { ?>
                                <div class="mb-3">
                                    <h2 class="h5">Trophy Groups</h2>
                                    <div class="vstack gap-3">
                                        <?php foreach ($groupChanges as $groupChange) { ?>
                                            <?php $groupChangedFields = $groupChange['changedFields'] ?? ['name' => false, 'detail' => false, 'icon_url' => false]; ?>
                                            <div class="diff-block">
                                                <div class="diff-line diff-header">
                                                    <span class="diff-symbol">@</span>
                                                    <div class="diff-context">
                                                        <span>Group <span class="badge text-bg-secondary"><?= htmlentities($groupChange['group_id'], ENT_QUOTES, 'UTF-8'); ?></span></span>
                                                        <?php if ($groupChange['isNewRow'] ?? false) { ?>
                                                            <span class="badge text-bg-success">New</span>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                                <?php if ($groupChangedFields['name'] ?? false) { ?>
                                                    <?= $renderDiffTextLines('Name', $groupChange['previous']['name'] ?? null, $groupChange['name'] ?? null); ?>
                                                <?php } ?>
                                                <?php if ($groupChangedFields['detail'] ?? false) { ?>
                                                    <?= $renderDiffTextLines('Detail', $groupChange['previous']['detail'] ?? null, $groupChange['detail'] ?? null, true); ?>
                                                <?php } ?>
                                                <?php if ($groupChangedFields['icon_url'] ?? false) { ?>
                                                    <?= $renderDiffIconLines('Icon', $groupChange['previous']['icon_url'] ?? null, $groupChange['icon_url'] ?? null, $resolveGroupIconPath, '/img/group/', $groupChange['name'] ?? '', 'diff-image-md object-fit-cover'); ?>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>

                            <?php if ($trophyChanges !== []) { ?>
                                <div class="vstack gap-3">
                                    <h2 class="h5">Trophies</h2>
                                    <?php foreach ($trophyChanges as $trophyChange) { ?>
                                        <?php $trophyChangedFields = $trophyChange['changedFields'] ?? ['name' => false, 'detail' => false, 'icon_url' => false, 'progress_target_value' => false]; ?>
                                        <div class="diff-block">
                                            <div class="diff-line diff-header">
                                                <span class="diff-symbol">@</span>
                                                <div class="diff-context">
                                                    <span>Group <span class="badge text-bg-secondary"><?= htmlentities($trophyChange['group_id'], ENT_QUOTES, 'UTF-8'); ?></span></span>
                                                    <span>#<?= (int) $trophyChange['order_id']; ?></span>
                                                    <?php if ($trophyChange['is_unobtainable'] ?? false) { ?>
                                                        <span class="badge text-bg-warning" title="This trophy is unobtainable and not accounted for on any leaderboard.">Unobtainable</span>
                                                    <?php } ?>
                                                    <?php if ($trophyChange['isNewRow'] ?? false) { ?>
                                                        <span class="badge text-bg-success">New</span>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <?php if ($trophyChangedFields['name'] ?? false) { ?>
                                                <?= $renderDiffTextLines('Name', $trophyChange['previous']['name'] ?? null, $trophyChange['name'] ?? null); ?>
                                            <?php } ?>
                                            <?php if ($trophyChangedFields['detail'] ?? false) { ?>
                                                <?= $renderDiffTextLines('Detail', $trophyChange['previous']['detail'] ?? null, $trophyChange['detail'] ?? null, true); ?>
                                            <?php } ?>
                                            <?php if ($trophyChangedFields['progress_target_value'] ?? false) { ?>
                                                <?php
                                                $previousTarget = $trophyChange['previous']['progress_target_value'] ?? null;
                                                $currentTarget = $trophyChange['progress_target_value'] ?? null;
                                                ?>
                                                <?= $renderDiffTextLines('Target', $previousTarget === null ? null : (string) $previousTarget, $currentTarget === null ? null : (string) $currentTarget); ?>
                                            <?php } ?>
                                            <?php if ($trophyChangedFields['icon_url'] ?? false) { ?>
                                                <?= $renderDiffIconLines('Icon', $trophyChange['previous']['icon_url'] ?? null, $trophyChange['icon_url'] ?? null, $resolveTrophyIconPath, '/img/trophy/', $trophyChange['name'] ?? '', 'diff-image-md object-fit-scale'); ?>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
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
