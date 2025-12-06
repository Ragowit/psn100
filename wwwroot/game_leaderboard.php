<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/GameLeaderboardPageContext.php';

$pageContext = GameLeaderboardPageContext::fromGlobals(
    $database,
    $utility,
    isset($gameId) ? (int) $gameId : null,
    isset($player) ? (string) $player : null,
    $_GET ?? []
);

if ($pageContext->shouldRedirect()) {
    header('Location: ' . $pageContext->getRedirectLocation(), true, $pageContext->getRedirectStatusCode());
    exit();
}

$game = $pageContext->getGame();
$gameHeaderData = $pageContext->getGameHeaderData();
$filter = $pageContext->getFilter();
$totalPlayers = $pageContext->getTotalPlayers();
$currentPage = $pageContext->getCurrentPage();
$limit = $pageContext->getLimit();
$offset = $pageContext->getOffset();
$totalPagesCount = $pageContext->getTotalPagesCount();
$rows = $pageContext->getRows();
$accountId = $pageContext->getPlayerAccountId();
$gameSlug = $pageContext->getGameSlug();

$title = $pageContext->getTitle();
require_once("header.php");
?>

<main class="container">
    <?php
    require_once("game_header.php");
    ?>

    <div class="p-3 mb-3">
        <div class="row">
            <div class="col-3">
            </div>

            <div class="col-6 text-center">
                <div class="btn-group">
                    <a class="btn btn-outline-primary" href="/game/<?= $gameSlug; ?><?= (isset($player) ? '/' . $player : ''); ?>">Trophies</a>
                    <a class="btn btn-primary active" href="/game-leaderboard/<?= $gameSlug; ?><?= (isset($player) ? '/' . $player : ''); ?>">Leaderboard</a>
                    <a class="btn btn-outline-primary" href="/game-recent-players/<?= $gameSlug; ?><?= (isset($player) ? '/' . $player : ''); ?>">Recent Players</a>
                    <a class="btn btn-outline-primary" href="/game-history/<?= $gameSlug; ?><?= (isset($player) ? '/' . $player : ''); ?>">History</a>
                </div>
            </div>

            <div class="col-3">
            </div>
        </div>
    </div>

    <div class="bg-body-tertiary p-3 rounded">
        <div class="row">
            <div class="col-12">
                <div class="table-responsive-xxl">
                    <table class="table">
                        <thead>
                            <tr class="text-uppercase">
                                <th scope="col">Rank</th>
                                <th scope="col">User</th>
                                <th scope="col" class="text-center">Date</th>
                                <th scope="col" class="text-center">Progress</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $rank = $offset;
                            foreach ($rows as $row) {
                                $countryName = $row->getCountryName($utility);
                                $paramsAvatar = $row->getAvatarQueryParameters($filter);
                                $paramsCountry = $row->getCountryQueryParameters($filter);
                                $playerName = $row->getOnlineId();
                                $playerUrl = '/game/' . $gameSlug . '/' . rawurlencode($playerName);
                                ?>
                                <tr<?= $row->matchesAccountId($accountId) ? " class='table-primary'" : ""; ?>>
                                    <th class="align-middle" style="width: 2rem;" scope="row"><?= ++$rank; ?></th>

                                    <td>
                                        <div class="hstack gap-3">
                                            <div>
                                                <a href="?<?= http_build_query($paramsAvatar); ?>">
                                                    <img src="/img/avatar/<?= htmlspecialchars($row->getAvatarUrl(), ENT_QUOTES, 'UTF-8'); ?>" alt="" height="50" width="50" />
                                                </a>
                                            </div>

                                            <div>
                                                <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="<?= $playerUrl; ?>">
                                                    <?= htmlspecialchars($playerName, ENT_QUOTES, 'UTF-8'); ?>
                                                </a>
                                                <?php if ($row->hasHiddenTrophies()) { ?>
                                                    <span style='color: #9d9d9d; font-weight: bold;'>(H)</span>
                                                <?php } ?>
                                            </div>

                                            <div class="ms-auto">
                                                <a href="?<?= http_build_query($paramsCountry); ?>">
                                                    <img src="/img/country/<?= htmlspecialchars($row->getCountryCode(), ENT_QUOTES, 'UTF-8'); ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
                                                </a>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="align-middle text-center" style="white-space: nowrap; width: 5rem;">
                                        <span
                                            class="js-leaderboard-date"
                                            data-timestamp="<?= htmlspecialchars($row->getLastKnownDate(), ENT_QUOTES, 'UTF-8'); ?>"
                                        ></span>
                                    </td>

                                    <td class="align-middle text-center" style="white-space: nowrap; width: 10rem;">
                                        <div class="vstack gap-1">
                                            <div>
                                                <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $row->getPlatinumCount(); ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $row->getGoldCount(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $row->getSilverCount(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $row->getBronzeCount(); ?></span>
                                            </div>

                                            <div>
                                                <div class="progress" role="progressbar" aria-label="Player game progress" aria-valuenow="<?= $row->getProgress(); ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <div class="progress-bar" style="width: <?= $row->getProgress(); ?>%">
                                                        <?= $row->getProgress(); ?>%
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <p class="text-center">
                <?= ($totalPlayers == 0 ? "0" : $offset + 1); ?>-<?= min($offset + $limit, $totalPlayers); ?> of <?= number_format($totalPlayers) ?>
            </p>
        </div>
        <div class="col-12">
            <?= $paginationRenderer->render(
                $currentPage,
                $totalPagesCount,
                static fn (int $pageNumber): array => $filter->withPage($pageNumber),
                'Game Leaderboard page navigation'
            ); ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const formatter = new LocalizedDateFormatter('.js-leaderboard-date');
    formatter.initialize();
});
</script>

<?php
require_once("footer.php");
?>
