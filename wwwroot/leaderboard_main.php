<?php
require_once 'classes/Leaderboard/TrophyLeaderboardPageContext.php';

$trophyLeaderboardPageContext = TrophyLeaderboardPageContext::fromGlobals($database, $utility, $_GET ?? []);
$title = $trophyLeaderboardPageContext->getTitle();
require_once("header.php");

$playerLeaderboardPage = $trophyLeaderboardPageContext->getLeaderboardPage();
$rows = $trophyLeaderboardPageContext->getRows();
$filterParameters = $trophyLeaderboardPageContext->getFilterQueryParameters();
?>

<main class="container">
    <div class="row">
        <div class="col-12">
            <div class="hstack gap-3">
                <h1>PSN Trophy Leaderboard</h1>
                <div class="bg-body-tertiary p-3 rounded">
                    <div class="btn-group">
                        <a class="btn btn-primary active" href="/leaderboard/trophy">Trophy</a>
                        <a class="btn btn-outline-primary" href="/leaderboard/rarity?<?= http_build_query($filterParameters); ?>">Rarity</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-body-tertiary p-3 rounded mt-3">
        <div class="row">
            <div class="col-12">
                <div class="table-responsive-xxl">
                    <table class="table">
                        <thead>
                            <tr class="text-uppercase">
                                <?php
                                if ($trophyLeaderboardPageContext->shouldShowCountryRank()) {
                                    ?>
                                    <th scope="col" class="text-center">Country<br>Rank</th>
                                    <?php
                                } else {
                                    ?>
                                    <th scope="col" class="text-center">Rank</th>
                                    <?php
                                }
                                ?>
                                <th scope="col">User</th>
                                <th scope="col" class="text-center">Level</th>
                                <th scope="col" class="text-center">Platinum</th>
                                <th scope="col" class="text-center">Gold</th>
                                <th scope="col" class="text-center">Silver</th>
                                <th scope="col" class="text-center">Bronze</th>
                                <th scope="col" class="text-center">Trophies</th>
                                <th scope="col" class="text-center">Points</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($rows as $row) { ?>
                                <tr id="<?= htmlspecialchars($row->getRowId(), ENT_QUOTES, 'UTF-8'); ?>"<?= $row->getRowCssClass() !== '' ? ' class="' . $row->getRowCssClass() . '"' : ''; ?>>
                                    <th scope="row" class="text-center align-middle">
                                        <?= $row->getRankCellHtml(); ?>
                                    </th>
                                    <td class="align-middle">
                                        <div class="hstack gap-3">
                                            <div>
                                                <a href="?<?= http_build_query($row->getAvatarQueryParameters()); ?>">
                                                    <img src="/img/avatar/<?= htmlspecialchars($row->getAvatarUrl(), ENT_QUOTES, 'UTF-8'); ?>" alt="" height="50" width="50" />
                                                </a>
                                            </div>

                                            <div>
                                                <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/player/<?= htmlspecialchars($row->getOnlineId(), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($row->getOnlineId(), ENT_QUOTES, 'UTF-8'); ?></a>
                                            </div>

                                            <div class="ms-auto">
                                                <a href="?<?= http_build_query($row->getCountryQueryParameters()); ?>">
                                                    <?php $countryName = htmlspecialchars($row->getCountryName(), ENT_QUOTES, 'UTF-8'); ?>
                                                    <img src="/img/country/<?= htmlspecialchars($row->getCountryCode(), ENT_QUOTES, 'UTF-8'); ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <img src="/img/star.svg" class="mb-1" alt="Level" title="Level" height="18" /> <?= number_format($row->getLevel()); ?>
                                        <div class="progress" title="<?= $row->getProgress(); ?>%">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $row->getProgress(); ?>%" aria-valuenow="<?= $row->getProgress(); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </td>
                                    <td class="text-center align-middle"><img src="/img/trophy-platinum.svg" alt="Platinum" height="18" /> <span class="trophy-platinum"><?= number_format($row->getPlatinumCount()); ?></span></td>
                                    <td class="text-center align-middle"><img src="/img/trophy-gold.svg" alt="Gold" height="18" /> <span class="trophy-gold"><?= number_format($row->getGoldCount()); ?></span></td>
                                    <td class="text-center align-middle"><img src="/img/trophy-silver.svg" alt="Silver" height="18" /> <span class="trophy-silver"><?= number_format($row->getSilverCount()); ?></span></td>
                                    <td class="text-center align-middle"><img src="/img/trophy-bronze.svg" alt="Bronze" height="18" /> <span class="trophy-bronze"><?= number_format($row->getBronzeCount()); ?></span></td>
                                    <td class="text-center align-middle"><?= number_format($row->getTotalTrophies()); ?></td>
                                    <td class="text-center align-middle"><?= number_format($row->getPoints()); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <p class="text-center">
                <?= ($playerLeaderboardPage->getTotalPlayers() === 0 ? '0' : $playerLeaderboardPage->getRangeStart()); ?>-<?= $playerLeaderboardPage->getRangeEnd(); ?> of <?= number_format($playerLeaderboardPage->getTotalPlayers()); ?>
            </p>
        </div>
        <div class="col-12">
            <?= $paginationRenderer->render(
                $playerLeaderboardPage->getCurrentPage(),
                $playerLeaderboardPage->getLastPage(),
                static fn (int $pageNumber): array => $playerLeaderboardPage->getPageQueryParameters($pageNumber),
                'Leaderboard page navigation'
            ); ?>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
