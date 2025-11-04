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

$renderDiffBlock = static function (?string $diff): string {
    if ($diff === null || $diff === '') {
        return '';
    }

    $lines = explode("\n", $diff);
    $htmlLines = [];

    foreach ($lines as $line) {
        $class = 'diff-line';

        if (str_starts_with($line, '+++')) {
            $class .= ' diff-line-file diff-line-add';
        } elseif (str_starts_with($line, '---')) {
            $class .= ' diff-line-file diff-line-remove';
        } elseif (str_starts_with($line, '@@')) {
            $class .= ' diff-line-hunk';
        } elseif ($line !== '' && $line[0] === '+') {
            $class .= ' diff-line-add';
        } elseif ($line !== '' && $line[0] === '-') {
            $class .= ' diff-line-remove';
        } elseif ($line !== '' && $line[0] === ' ') {
            $class .= ' diff-line-context';
        } else {
            $class .= ' diff-line-other';
        }

        $htmlLines[] = '<span class="' . $class . '">' . htmlentities($line, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    return '<pre class="diff-block bg-body-secondary-subtle small p-3 rounded mb-0">' . implode("\n", $htmlLines) . '</pre>';
};

$resolveTitleIconPath = static function (?string $iconUrl) use ($game): ?string {
    if ($iconUrl === null || $iconUrl === '') {
        return null;
    }

    if ($iconUrl === '.png') {
        if (str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2')) {
            return '../missing-ps5-game-and-trophy.png';
        }

        return '../missing-ps4-game.png';
    }

    return $iconUrl;
};

$resolveGroupIconPath = static function (?string $iconUrl) use ($resolveTitleIconPath): ?string {
    return $resolveTitleIconPath($iconUrl);
};

$resolveTrophyIconPath = static function (?string $iconUrl) use ($game): ?string {
    if ($iconUrl === null || $iconUrl === '') {
        return null;
    }

    if ($iconUrl === '.png') {
        if (str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2')) {
            return '../missing-ps5-game-and-trophy.png';
        }

        return '../missing-ps4-trophy.png';
    }

    return $iconUrl;
};
?>

<main class="container">
    <?php require_once 'game_header.php'; ?>

    <style>
        .diff-block {
            font-family: var(--bs-font-monospace, monospace);
            white-space: pre-wrap;
        }

        .diff-line-add {
            color: var(--bs-success-text-emphasis);
        }

        .diff-line-remove {
            color: var(--bs-danger-text-emphasis);
        }

        .diff-line-hunk {
            color: var(--bs-primary-text-emphasis);
            font-weight: 600;
        }

        .diff-line-file {
            font-weight: 600;
        }

        .diff-line-context {
            color: var(--bs-secondary-color);
        }
    </style>

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
                            <?php $titleDiffs = $entry['titleDiffs'] ?? ['detail' => null, 'icon_url' => null, 'set_version' => null]; ?>
                            <?php if ($hasTitleChanges) { ?>
                                <div class="mb-4">
                                    <h2 class="h5">Title Changes</h2>

                                    <?php if (($titleDiffs['set_version'] ?? null) !== null) { ?>
                                        <div class="mb-3">
                                            <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Version</div>
                                            <?= $renderDiffBlock($titleDiffs['set_version']); ?>
                                        </div>
                                    <?php } ?>

                                    <?php if (($titleDiffs['detail'] ?? null) !== null) { ?>
                                        <div class="mb-3">
                                            <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Detail</div>
                                            <?= $renderDiffBlock($titleDiffs['detail']); ?>
                                        </div>
                                    <?php } ?>

                                    <?php if (($titleDiffs['icon_url'] ?? null) !== null) { ?>
                                        <div class="mb-3">
                                            <div class="text-uppercase small text-body-secondary fw-semibold mb-2">Icon</div>
                                            <?php
                                            $previousIconPath = $resolveTitleIconPath($entry['titlePrevious']['icon_url'] ?? null);
                                            $currentIconPath = $resolveTitleIconPath($titleChange['icon_url'] ?? null);
                                            ?>
                                            <?php if ($previousIconPath !== null || $currentIconPath !== null) { ?>
                                                <div class="d-flex flex-wrap gap-3 align-items-start mb-2">
                                                    <?php if ($previousIconPath !== null) { ?>
                                                        <div class="text-center">
                                                            <div class="small text-body-secondary mb-1">Previous</div>
                                                            <img class="object-fit-scale border rounded" style="height: 5.5rem;" src="/img/title/<?= htmlentities($previousIconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Previous icon for <?= htmlentities($game->getName(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                    <?php } ?>
                                                    <?php if ($currentIconPath !== null) { ?>
                                                        <div class="text-center">
                                                            <div class="small text-body-secondary mb-1">Current</div>
                                                            <img class="object-fit-scale border border-success border-2 rounded" style="height: 5.5rem;" src="/img/title/<?= htmlentities($currentIconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Current icon for <?= htmlentities($game->getName(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                            <?= $renderDiffBlock($titleDiffs['icon_url']); ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>

                            <?php $groupChanges = $entry['groups'] ?? []; ?>
                            <?php if ($groupChanges !== []) { ?>
                                <div class="mb-4">
                                    <h2 class="h5">Trophy Groups</h2>
                                    <div class="vstack gap-3">
                                        <?php foreach ($groupChanges as $groupChange) { ?>
                                            <?php $groupDiffs = $groupChange['diffs'] ?? ['name' => null, 'detail' => null, 'icon_url' => null]; ?>
                                            <?php $groupPrevious = $groupChange['previousValues'] ?? ['name' => null, 'detail' => null, 'icon_url' => null]; ?>
                                            <div class="border rounded p-3">
                                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge text-bg-secondary"><?= htmlentities($groupChange['group_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <span class="small text-body-secondary text-uppercase">Group</span>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <?php if ($groupChange['isNewRow'] ?? false) { ?>
                                                            <span class="badge text-bg-success">New</span>
                                                        <?php } ?>
                                                    </div>
                                                </div>

                                                <?php if (($groupDiffs['name'] ?? null) !== null) { ?>
                                                    <div class="mb-3">
                                                        <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Name</div>
                                                        <?= $renderDiffBlock($groupDiffs['name']); ?>
                                                    </div>
                                                <?php } ?>

                                                <?php if (($groupDiffs['detail'] ?? null) !== null) { ?>
                                                    <div class="mb-3">
                                                        <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Detail</div>
                                                        <?= $renderDiffBlock($groupDiffs['detail']); ?>
                                                    </div>
                                                <?php } ?>

                                                <?php if (($groupDiffs['icon_url'] ?? null) !== null) { ?>
                                                    <div class="mb-3">
                                                        <div class="text-uppercase small text-body-secondary fw-semibold mb-2">Icon</div>
                                                        <?php
                                                        $previousGroupIconPath = $resolveGroupIconPath($groupPrevious['icon_url'] ?? null);
                                                        $currentGroupIconPath = $resolveGroupIconPath($groupChange['icon_url'] ?? null);
                                                        ?>
                                                        <?php if ($previousGroupIconPath !== null || $currentGroupIconPath !== null) { ?>
                                                            <div class="d-flex flex-wrap gap-3 align-items-start mb-2">
                                                                <?php if ($previousGroupIconPath !== null) { ?>
                                                                    <div class="text-center">
                                                                        <div class="small text-body-secondary mb-1">Previous</div>
                                                                        <img class="object-fit-cover border rounded" style="height: 3.5rem;" src="/img/group/<?= htmlentities($previousGroupIconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($groupPrevious['name'] ?? 'Previous group icon', ENT_QUOTES, 'UTF-8'); ?>">
                                                                    </div>
                                                                <?php } ?>
                                                                <?php if ($currentGroupIconPath !== null) { ?>
                                                                    <div class="text-center">
                                                                        <div class="small text-body-secondary mb-1">Current</div>
                                                                        <img class="object-fit-cover border border-success border-2 rounded" style="height: 3.5rem;" src="/img/group/<?= htmlentities($currentGroupIconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($groupChange['name'] ?? 'Current group icon', ENT_QUOTES, 'UTF-8'); ?>">
                                                                    </div>
                                                                <?php } ?>
                                                            </div>
                                                        <?php } ?>
                                                        <?= $renderDiffBlock($groupDiffs['icon_url']); ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>

                            <?php $trophyChanges = $entry['trophies'] ?? []; ?>
                            <?php if ($trophyChanges !== []) { ?>
                                <div>
                                    <h2 class="h5">Trophies</h2>
                                    <div class="vstack gap-3">
                                        <?php foreach ($trophyChanges as $trophyChange) { ?>
                                            <?php $trophyDiffs = $trophyChange['diffs'] ?? ['name' => null, 'detail' => null, 'icon_url' => null, 'progress_target_value' => null]; ?>
                                            <?php $trophyPrevious = $trophyChange['previousValues'] ?? ['name' => null, 'detail' => null, 'icon_url' => null, 'progress_target_value' => null]; ?>
                                            <div class="border rounded p-3">
                                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                                        <span class="badge text-bg-secondary"><?= htmlentities($trophyChange['group_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <span class="badge text-bg-primary">#<?= (int) $trophyChange['order_id']; ?></span>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <?php if ($trophyChange['isNewRow'] ?? false) { ?>
                                                            <span class="badge text-bg-success">New</span>
                                                        <?php } ?>
                                                        <?php if ($trophyChange['is_unobtainable'] ?? false) { ?>
                                                            <span class="badge text-bg-warning" title="This trophy is unobtainable and not accounted for on any leaderboard.">Unobtainable</span>
                                                        <?php } ?>
                                                    </div>
                                                </div>

                                                <?php if (($trophyDiffs['name'] ?? null) !== null) { ?>
                                                    <div class="mb-3">
                                                        <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Name</div>
                                                        <?= $renderDiffBlock($trophyDiffs['name']); ?>
                                                    </div>
                                                <?php } ?>

                                                <?php if (($trophyDiffs['detail'] ?? null) !== null) { ?>
                                                    <div class="mb-3">
                                                        <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Detail</div>
                                                        <?= $renderDiffBlock($trophyDiffs['detail']); ?>
                                                    </div>
                                                <?php } ?>

                                                <?php if (($trophyDiffs['progress_target_value'] ?? null) !== null) { ?>
                                                    <div class="mb-3">
                                                        <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Target</div>
                                                        <?= $renderDiffBlock($trophyDiffs['progress_target_value']); ?>
                                                    </div>
                                                <?php } ?>

                                                <?php if (($trophyDiffs['icon_url'] ?? null) !== null) { ?>
                                                    <div class="mb-3">
                                                        <div class="text-uppercase small text-body-secondary fw-semibold mb-2">Icon</div>
                                                        <?php
                                                        $previousTrophyIconPath = $resolveTrophyIconPath($trophyPrevious['icon_url'] ?? null);
                                                        $currentTrophyIconPath = $resolveTrophyIconPath($trophyChange['icon_url'] ?? null);
                                                        ?>
                                                        <?php if ($previousTrophyIconPath !== null || $currentTrophyIconPath !== null) { ?>
                                                            <div class="d-flex flex-wrap gap-3 align-items-start mb-2">
                                                                <?php if ($previousTrophyIconPath !== null) { ?>
                                                                    <div class="text-center">
                                                                        <div class="small text-body-secondary mb-1">Previous</div>
                                                                        <img class="object-fit-scale border rounded" style="height: 3.5rem;" src="/img/trophy/<?= htmlentities($previousTrophyIconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($trophyPrevious['name'] ?? 'Previous trophy icon', ENT_QUOTES, 'UTF-8'); ?>">
                                                                    </div>
                                                                <?php } ?>
                                                                <?php if ($currentTrophyIconPath !== null) { ?>
                                                                    <div class="text-center">
                                                                        <div class="small text-body-secondary mb-1">Current</div>
                                                                        <img class="object-fit-scale border border-success border-2 rounded" style="height: 3.5rem;" src="/img/trophy/<?= htmlentities($currentTrophyIconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($trophyChange['name'] ?? 'Current trophy icon', ENT_QUOTES, 'UTF-8'); ?>">
                                                                    </div>
                                                                <?php } ?>
                                                            </div>
                                                        <?php } ?>
                                                        <?= $renderDiffBlock($trophyDiffs['icon_url']); ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
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
