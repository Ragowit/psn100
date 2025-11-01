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
                                $setVersion = $titleChange['set_version'] ?? null;
                                ?>
                                <span class="fw-semibold">Version <?= htmlentities($setVersion ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="text-body-secondary small">
                                <?= htmlentities($entry['discoveredAt']->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?> UTC
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($titleChange !== null) { ?>
                                <div class="row g-3 align-items-center mb-3">
                                    <div class="col-12 col-md-2 text-center text-md-start">
                                        <?php
                                        $iconUrl = $titleChange['icon_url'] ?? '';
                                        $iconPath = ($iconUrl === '.png')
                                            ? ((str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2'))
                                                ? '../missing-ps5-game-and-trophy.png'
                                                : '../missing-ps4-game.png')
                                            : $iconUrl;
                                        ?>
                                        <img class="object-fit-scale" style="height: 5.5rem;" src="/img/title/<?= htmlentities($iconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($game->getName(), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="col-12 col-md-10">
                                        <?php if (($titleChange['detail'] ?? '') !== '') { ?>
                                            <div><?= nl2br(htmlentities((string) $titleChange['detail'], ENT_QUOTES, 'UTF-8')); ?></div>
                                        <?php } else { ?>
                                            <div class="text-body-secondary"><em>No title detail provided.</em></div>
                                        <?php } ?>
                                    </div>
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
                                                    <tr>
                                                        <td><span class="badge text-bg-secondary"><?= htmlentities($groupChange['group_id'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                        <td><?= htmlentities($groupChange['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?= nl2br(htmlentities($groupChange['detail'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
                                                        <td class="text-center">
                                                            <?php
                                                            $groupIconUrl = $groupChange['icon_url'] ?? '';
                                                            $groupIconPath = ($groupIconUrl === '.png')
                                                                ? ((str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2'))
                                                                    ? '../missing-ps5-game-and-trophy.png'
                                                                    : '../missing-ps4-game.png')
                                                                : $groupIconUrl;
                                                            ?>
                                                            <img class="object-fit-cover" style="height: 3.5rem;" src="/img/group/<?= htmlentities($groupIconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($groupChange['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
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
                                                    <tr>
                                                        <td><span class="badge text-bg-secondary"><?= htmlentities($trophyChange['group_id'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                        <td><?= (int) $trophyChange['order_id']; ?></td>
                                                        <td><?= htmlentities($trophyChange['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?= nl2br(htmlentities($trophyChange['detail'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
                                                        <td><?= $trophyChange['progress_target_value'] === null ? '&mdash;' : htmlentities((string) $trophyChange['progress_target_value'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td class="text-center">
                                                            <?php
                                                            $trophyIconUrl = $trophyChange['icon_url'] ?? '';
                                                            $trophyIconPath = ($trophyIconUrl === '.png')
                                                                ? ((str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2'))
                                                                    ? '../missing-ps5-game-and-trophy.png'
                                                                    : '../missing-ps4-trophy.png')
                                                                : $trophyIconUrl;
                                                            ?>
                                                            <img class="object-fit-scale" style="height: 3.5rem;" src="/img/trophy/<?= htmlentities($trophyIconPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($trophyChange['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
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
</main>

<?php require_once 'footer.php'; ?>
