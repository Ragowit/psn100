<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/GameHistoryPage.php';
require_once __DIR__ . '/classes/GameHistoryRenderer.php';

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
$historyRenderer = new GameHistoryRenderer();

require_once 'header.php';
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
                                $titleIsNewRow = $entry['isTitleNewRow'] ?? false;
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
                            <?php if ($titleChange !== null && $hasTitleChanges && (($titleHighlights['detail'] ?? false) || ($titleHighlights['icon_url'] ?? false))) { ?>
                                <div class="row g-3 align-items-start mb-3">
                                    <?php if ($titleHighlights['icon_url'] ?? false) { ?>
                                        <div class="col-12 col-md-4 col-lg-3">
                                            <?= $historyRenderer->renderIconDiff($titleFieldDiffs['icon_url'] ?? null, $game, 'title', $game->getName(), $titleIsNewRow); ?>
                                        </div>
                                    <?php } ?>
                                    <?php if ($titleHighlights['detail'] ?? false) { ?>
                                        <div class="col-12 <?= ($titleHighlights['icon_url'] ?? false) ? 'col-md-8 col-lg-9' : 'col-md-12'; ?>">
                                            <?= $historyRenderer->renderTextDiff($titleFieldDiffs['detail'] ?? null, true, $titleIsNewRow); ?>
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
                                                                <?= $historyRenderer->renderSingleText($groupChange['name'] ?? null); ?>
                                                            <?php } elseif ($groupChangedFields['name'] ?? false) { ?>
                                                                <?= $historyRenderer->renderTextDiff($groupFieldDiffs['name'] ?? null); ?>
                                                            <?php } else { ?>
                                                                <?= $historyRenderer->renderSingleText($groupChange['name'] ?? null); ?>
                                                            <?php } ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($groupIsNewRow) { ?>
                                                                <?= $historyRenderer->renderSingleText($groupChange['detail'] ?? null, true); ?>
                                                            <?php } elseif ($groupChangedFields['detail'] ?? false) { ?>
                                                                <?= $historyRenderer->renderTextDiff($groupFieldDiffs['detail'] ?? null, true); ?>
                                                            <?php } else { ?>
                                                                <?= $historyRenderer->renderSingleText($groupChange['detail'] ?? null, true); ?>
                                                            <?php } ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($groupIsNewRow) { ?>
                                                                <?= $historyRenderer->renderSingleIcon($groupChange['icon_url'] ?? null, $game, 'group', $groupChange['name'] ?? ''); ?>
                                                            <?php } elseif ($groupChangedFields['icon_url'] ?? false) { ?>
                                                                <?= $historyRenderer->renderIconDiff($groupFieldDiffs['icon_url'] ?? null, $game, 'group', $groupChange['name'] ?? ''); ?>
                                                            <?php } else { ?>
                                                                <?= $historyRenderer->renderSingleIcon($groupChange['icon_url'] ?? null, $game, 'group', $groupChange['name'] ?? ''); ?>
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
                                                                <?= $historyRenderer->renderSingleText($trophyChange['name'] ?? null); ?>
                                                            <?php } elseif ($trophyChangedFields['name'] ?? false) { ?>
                                                                <?= $historyRenderer->renderTextDiff($trophyFieldDiffs['name'] ?? null); ?>
                                                            <?php } else { ?>
                                                                <?= $historyRenderer->renderSingleText($trophyChange['name'] ?? null); ?>
                                                            <?php } ?>
                                                        </td>
                                                        <td class="<?= (($trophyChange['isNewRow'] ?? false) || ($trophyChangedFields['detail'] ?? false)) ? 'table-success' : ''; ?>">
                                                            <?php if ($trophyIsNewRow) { ?>
                                                                <?= $historyRenderer->renderSingleText($trophyChange['detail'] ?? null, true); ?>
                                                            <?php } elseif ($trophyChangedFields['detail'] ?? false) { ?>
                                                                <?= $historyRenderer->renderTextDiff($trophyFieldDiffs['detail'] ?? null, true); ?>
                                                            <?php } else { ?>
                                                                <?= $historyRenderer->renderSingleText($trophyChange['detail'] ?? null, true); ?>
                                                            <?php } ?>
                                                        </td>
                                                        <td class="<?= (($trophyChange['isNewRow'] ?? false) || ($trophyChangedFields['progress_target_value'] ?? false)) ? 'table-success' : ''; ?>">
                                                            <?php if ($trophyIsNewRow) { ?>
                                                                <?= $historyRenderer->renderSingleNumber($trophyChange['progress_target_value'] ?? null); ?>
                                                            <?php } elseif ($trophyChangedFields['progress_target_value'] ?? false) { ?>
                                                                <?= $historyRenderer->renderNumberDiff($trophyFieldDiffs['progress_target_value'] ?? null); ?>
                                                            <?php } else { ?>
                                                                <?= $historyRenderer->renderSingleNumber($trophyChange['progress_target_value'] ?? null); ?>
                                                            <?php } ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($trophyIsNewRow) { ?>
                                                                <?= $historyRenderer->renderSingleIcon($trophyChange['icon_url'] ?? null, $game, 'trophy', $trophyChange['name'] ?? ''); ?>
                                                            <?php } elseif ($trophyChangedFields['icon_url'] ?? false) { ?>
                                                                <?= $historyRenderer->renderIconDiff($trophyFieldDiffs['icon_url'] ?? null, $game, 'trophy', $trophyChange['name'] ?? ''); ?>
                                                            <?php } else { ?>
                                                                <?= $historyRenderer->renderSingleIcon($trophyChange['icon_url'] ?? null, $game, 'trophy', $trophyChange['name'] ?? ''); ?>
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
