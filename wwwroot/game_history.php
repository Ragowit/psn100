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
                            <?php if ($titleChange !== null && $hasTitleChanges && (($titleHighlights['detail'] ?? false) || ($titleHighlights['icon_url'] ?? false))) { ?>
                                <div class="row g-3 align-items-center mb-3">
                                    <?php if ($titleHighlights['icon_url'] ?? false) { ?>
                                        <div class="col-12 col-md-2 text-center text-md-start">
                                            <?php
                                            $iconUrl = $titleChange['icon_url'] ?? '';
                                            $iconPath = ($iconUrl === '.png')
                                                ? ((str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2'))
                                                    ? '../missing-ps5-game-and-trophy.png'
                                                    : '../missing-ps4-game.png')
                                                : $iconUrl;
                                            ?>
                                            <img class="object-fit-scale border border-success border-2 rounded" style="height: 5.5rem;" src="/img/title/<?= htmlentities($iconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($game->getName(), ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                    <?php } ?>
                                    <?php if ($titleHighlights['detail'] ?? false) { ?>
                                        <div class="col-12 <?= ($titleHighlights['icon_url'] ?? false) ? 'col-md-10' : 'col-md-12'; ?>">
                                            <div class="p-2 border border-success rounded bg-success-subtle text-success-emphasis">
                                                <?= nl2br(htmlentities((string) $titleChange['detail'], ENT_QUOTES, 'UTF-8')); ?>
                                            </div>
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
                                                    $previousGroupValues = $groupChange['previousValues'] ?? null;
                                                    $hasGroupFieldChanges = in_array(true, $groupChangedFields, true);
                                                    $isNewGroupRow = $groupChange['isNewRow'] ?? false;
                                                    $showPreviousGroupRow = is_array($previousGroupValues) && $hasGroupFieldChanges;
                                                    ?>

                                                    <?php if ($showPreviousGroupRow) { ?>
                                                        <?php
                                                        $previousGroupIconUrl = $previousGroupValues['icon_url'] ?? '';
                                                        $previousGroupIconPath = ($previousGroupIconUrl === '.png')
                                                            ? ((str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2'))
                                                                ? '../missing-ps5-game-and-trophy.png'
                                                                : '../missing-ps4-game.png')
                                                            : $previousGroupIconUrl;
                                                        ?>
                                                        <tr class="diff-row diff-row-removed">
                                                            <td>
                                                                <span class="badge text-bg-secondary"><?= htmlentities($groupChange['group_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                            </td>
                                                            <td class="diff-cell diff-cell-removed <?= ($groupChangedFields['name'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                                <div class="diff-cell-content"><span class="visually-hidden">Previous value: </span><?= htmlentities($previousGroupValues['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                                            </td>
                                                            <td class="diff-cell diff-cell-removed <?= ($groupChangedFields['detail'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                                <div class="diff-cell-content"><span class="visually-hidden">Previous value: </span><?= nl2br(htmlentities($previousGroupValues['detail'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div>
                                                            </td>
                                                            <td class="text-center diff-cell diff-cell-removed diff-cell-icon <?= ($groupChangedFields['icon_url'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                                <div class="diff-cell-content">
                                                                    <span class="visually-hidden">Previous value: </span>
                                                                    <img class="object-fit-cover diff-icon" style="height: 3.5rem;" src="/img/group/<?= htmlentities($previousGroupIconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($previousGroupValues['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php } ?>

                                                    <?php
                                                    $groupIconUrl = $groupChange['icon_url'] ?? '';
                                                    $groupIconPath = ($groupIconUrl === '.png')
                                                        ? ((str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2'))
                                                            ? '../missing-ps5-game-and-trophy.png'
                                                            : '../missing-ps4-game.png')
                                                        : $groupIconUrl;
                                                    $groupRowClass = ($isNewGroupRow || $hasGroupFieldChanges) ? 'diff-row diff-row-added' : '';
                                                    $groupRowClassAttribute = $groupRowClass === '' ? '' : ' class="' . $groupRowClass . '"';
                                                    ?>
                                                    <tr<?= $groupRowClassAttribute; ?>>
                                                        <td>
                                                            <span class="badge text-bg-secondary"><?= htmlentities($groupChange['group_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </td>
                                                        <td class="diff-cell <?= ($isNewGroupRow || $hasGroupFieldChanges) ? 'diff-cell-added' : ''; ?> <?= ($groupChangedFields['name'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                            <div class="diff-cell-content"><span class="visually-hidden">Updated value: </span><?= htmlentities($groupChange['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                                        </td>
                                                        <td class="diff-cell <?= ($isNewGroupRow || $hasGroupFieldChanges) ? 'diff-cell-added' : ''; ?> <?= ($groupChangedFields['detail'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                            <div class="diff-cell-content"><span class="visually-hidden">Updated value: </span><?= nl2br(htmlentities($groupChange['detail'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div>
                                                        </td>
                                                        <td class="text-center diff-cell diff-cell-icon <?= ($isNewGroupRow || $hasGroupFieldChanges) ? 'diff-cell-added' : ''; ?> <?= ($groupChangedFields['icon_url'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                            <div class="diff-cell-content">
                                                                <span class="visually-hidden">Updated value: </span>
                                                                <img class="object-fit-cover diff-icon" style="height: 3.5rem;" src="/img/group/<?= htmlentities($groupIconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($groupChange['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                            </div>
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
                                                    $previousTrophyValues = $trophyChange['previousValues'] ?? null;
                                                    $hasFieldChanges = in_array(true, $trophyChangedFields, true);
                                                    $isNewTrophyRow = $trophyChange['isNewRow'] ?? false;
                                                    $showPreviousTrophyRow = is_array($previousTrophyValues) && $hasFieldChanges;
                                                    ?>

                                                    <?php if ($showPreviousTrophyRow) { ?>
                                                        <?php
                                                        $previousTargetValue = $previousTrophyValues['progress_target_value'] ?? null;
                                                        $previousIconUrl = $previousTrophyValues['icon_url'] ?? '';
                                                        $previousIconPath = ($previousIconUrl === '.png')
                                                            ? ((str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2'))
                                                                ? '../missing-ps5-game-and-trophy.png'
                                                                : '../missing-ps4-trophy.png')
                                                            : $previousIconUrl;
                                                        ?>
                                                        <tr class="diff-row diff-row-removed">
                                                            <td>
                                                                <span class="badge text-bg-secondary"><?= htmlentities($trophyChange['group_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                            </td>
                                                            <td>
                                                                <?php if ($trophyChange['is_unobtainable'] ?? false) { ?>
                                                                    <span class="badge text-bg-warning" title="This trophy is unobtainable and not accounted for on any leaderboard."><?= (int) $trophyChange['order_id']; ?></span>
                                                                <?php } else { ?>
                                                                    <?= (int) $trophyChange['order_id']; ?>
                                                                <?php } ?>
                                                            </td>
                                                            <td class="diff-cell diff-cell-removed <?= ($trophyChangedFields['name'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                                <div class="diff-cell-content"><span class="visually-hidden">Previous value: </span><?= htmlentities($previousTrophyValues['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                                            </td>
                                                            <td class="diff-cell diff-cell-removed <?= ($trophyChangedFields['detail'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                                <div class="diff-cell-content"><span class="visually-hidden">Previous value: </span><?= nl2br(htmlentities($previousTrophyValues['detail'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div>
                                                            </td>
                                                            <td class="diff-cell diff-cell-removed <?= ($trophyChangedFields['progress_target_value'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                                <div class="diff-cell-content"><span class="visually-hidden">Previous value: </span><?= $previousTargetValue === null ? '&mdash;' : htmlentities((string) $previousTargetValue, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            </td>
                                                            <td class="text-center diff-cell diff-cell-removed diff-cell-icon <?= ($trophyChangedFields['icon_url'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                                <div class="diff-cell-content">
                                                                    <span class="visually-hidden">Previous value: </span>
                                                                    <img class="object-fit-scale diff-icon" style="height: 3.5rem;" src="/img/trophy/<?= htmlentities($previousIconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($previousTrophyValues['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php } ?>

                                                    <?php
                                                    $trophyIconUrl = $trophyChange['icon_url'] ?? '';
                                                    $trophyIconPath = ($trophyIconUrl === '.png')
                                                        ? ((str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2'))
                                                            ? '../missing-ps5-game-and-trophy.png'
                                                            : '../missing-ps4-trophy.png')
                                                        : $trophyIconUrl;
                                                    $newRowClass = ($isNewTrophyRow || $hasFieldChanges) ? 'diff-row diff-row-added' : '';
                                                    $newRowClassAttribute = $newRowClass === '' ? '' : ' class="' . $newRowClass . '"';
                                                    ?>
                                                    <tr<?= $newRowClassAttribute; ?>>
                                                        <td>
                                                            <span class="badge text-bg-secondary"><?= htmlentities($trophyChange['group_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if ($trophyChange['is_unobtainable'] ?? false) { ?>
                                                                <span class="badge text-bg-warning" title="This trophy is unobtainable and not accounted for on any leaderboard."><?= (int) $trophyChange['order_id']; ?></span>
                                                            <?php } else { ?>
                                                                <?= (int) $trophyChange['order_id']; ?>
                                                            <?php } ?>
                                                        </td>
                                                        <td class="diff-cell <?= ($isNewTrophyRow || $hasFieldChanges) ? 'diff-cell-added' : ''; ?> <?= ($trophyChangedFields['name'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                            <div class="diff-cell-content"><span class="visually-hidden">Updated value: </span><?= htmlentities($trophyChange['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                                        </td>
                                                        <td class="diff-cell <?= ($isNewTrophyRow || $hasFieldChanges) ? 'diff-cell-added' : ''; ?> <?= ($trophyChangedFields['detail'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                            <div class="diff-cell-content"><span class="visually-hidden">Updated value: </span><?= nl2br(htmlentities($trophyChange['detail'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div>
                                                        </td>
                                                        <td class="diff-cell <?= ($isNewTrophyRow || $hasFieldChanges) ? 'diff-cell-added' : ''; ?> <?= ($trophyChangedFields['progress_target_value'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                            <div class="diff-cell-content"><span class="visually-hidden">Updated value: </span><?= $trophyChange['progress_target_value'] === null ? '&mdash;' : htmlentities((string) $trophyChange['progress_target_value'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        </td>
                                                        <td class="text-center diff-cell diff-cell-icon <?= ($isNewTrophyRow || $hasFieldChanges) ? 'diff-cell-added' : ''; ?> <?= ($trophyChangedFields['icon_url'] ?? false) ? 'diff-cell-highlight' : ''; ?>">
                                                            <div class="diff-cell-content">
                                                                <span class="visually-hidden">Updated value: </span>
                                                                <img class="object-fit-scale diff-icon" style="height: 3.5rem;" src="/img/trophy/<?= htmlentities($trophyIconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($trophyChange['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                            </div>
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
