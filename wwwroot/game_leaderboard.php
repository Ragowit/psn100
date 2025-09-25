<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/GameLeaderboardPage.php';

if (!isset($gameId)) {
    header("Location: /game/", true, 303);
    die();
}

$gameLeaderboardService = new GameLeaderboardService($database);
$gameHeaderService = new GameHeaderService($database);

try {
    $gameLeaderboardPage = GameLeaderboardPage::create(
        $gameLeaderboardService,
        $gameHeaderService,
        (int) $gameId,
        isset($player) ? (string) $player : null,
        $_GET ?? []
    );
} catch (GameNotFoundException $exception) {
    header("Location: /game/", true, 303);
    die();
} catch (GameLeaderboardPlayerNotFoundException $exception) {
    $slug = $utility->slugify($exception->getGameName());
    header("Location: /game/" . $exception->getGameId() . "-" . $slug, true, 303);
    die();
}

$game = $gameLeaderboardPage->getGame();
$gameHeaderData = $gameLeaderboardPage->getGameHeaderData();
$filter = $gameLeaderboardPage->getFilter();
$totalPlayers = $gameLeaderboardPage->getTotalPlayers();
$page = $gameLeaderboardPage->getPage();
$limit = $gameLeaderboardPage->getLimit();
$offset = $gameLeaderboardPage->getOffset();
$totalPagesCount = $gameLeaderboardPage->getTotalPagesCount();
$rows = $gameLeaderboardPage->getRows();
$accountId = $gameLeaderboardPage->getPlayerAccountId();

$title = $game["name"] ." Leaderboard ~ PSN 100%";
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
                    <a class="btn btn-outline-primary" href="/game/<?= $game["id"] ."-". $utility->slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Trophies</a>
                    <a class="btn btn-primary active" href="/game-leaderboard/<?= $game["id"] ."-". $utility->slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Leaderboard</a>
                    <a class="btn btn-outline-primary" href="/game-recent-players/<?= $game["id"] ."-". $utility->slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Recent Players</a>
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
                                $countryName = $utility->getCountryName($row["country"]);
                                $paramsAvatar = $filter->withAvatar($row["avatar_url"]);
                                $paramsCountry = $filter->withCountry($row["country"]);
                                ?>
                                <tr<?= ($accountId !== null && $row["account_id"] === $accountId) ? " class='table-primary'" : ""; ?>>
                                    <th class="align-middle" style="width: 2rem;" scope="row"><?= ++$rank; ?></th>

                                    <td>
                                        <div class="hstack gap-3">
                                            <div>
                                                <a href="?<?= http_build_query($paramsAvatar); ?>">
                                                    <img src="/img/avatar/<?= $row["avatar_url"]; ?>" alt="" height="50" width="50" />
                                                </a>
                                            </div>

                                            <div>
                                                <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $game["id"] ."-". $utility->slugify($game["name"]); ?>/<?= $row["name"]; ?>"><?= $row["name"]; ?></a>
                                                <?php
                                                if ($row["trophy_count_npwr"] < $row["trophy_count_sony"]) {
                                                    echo " <span style='color: #9d9d9d; font-weight: bold;'>(H)</span>";
                                                }
                                                ?>
                                            </div>

                                            <div class="ms-auto">
                                                <a href="?<?= http_build_query($paramsCountry); ?>">
                                                    <img src="/img/country/<?= $row["country"]; ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
                                                </a>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="align-middle text-center" style="white-space: nowrap; width: 5rem;">
                                        <span id="date<?= $rank; ?>"></span>
                                        <script>
                                            document.getElementById("date<?= $rank; ?>").innerHTML = new Date('<?= $row["last_known_date"]; ?> UTC').toLocaleString('sv-SE').replace(' ', '<br>');
                                        </script>
                                    </td>

                                    <td class="align-middle text-center" style="white-space: nowrap; width: 10rem;">
                                        <div class="vstack gap-1">
                                            <div>
                                                <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $row["platinum"]; ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $row["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $row["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $row["bronze"]; ?></span>
                                            </div>

                                            <div>
                                                <div class="progress" role="progressbar" aria-label="Player game progress" aria-valuenow="<?= $row["progress"]; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <div class="progress-bar" style="width: <?= $row["progress"]; ?>%"><?= $row["progress"]; ?>%</div>
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
            <nav aria-label="Game Leaderboard page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    if ($page > 1) {
                        $previousParams = $filter->withPage($page - 1);
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($previousParams); ?>" aria-label="Previous">&lt;</a></li>
                        <?php
                    }

                    if ($page > 3) {
                        $firstPageParams = $filter->withPage(1);
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($firstPageParams); ?>">1</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <?php
                    }

                    if ($page-2 > 0) {
                        $twoBackParams = $filter->withPage($page - 2);
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($twoBackParams); ?>"><?= $page-2; ?></a></li>
                        <?php
                    }

                    if ($page-1 > 0) {
                        $oneBackParams = $filter->withPage($page - 1);
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($oneBackParams); ?>"><?= $page-1; ?></a></li>
                        <?php
                    }
                    ?>

                    <?php
                    $currentPageParams = $filter->withPage($page);
                    ?>
                    <li class="page-item active" aria-current="page"><a class="page-link" href="?<?= http_build_query($currentPageParams); ?>"><?= $page; ?></a></li>

                    <?php
                    if ($page + 1 <= $totalPagesCount) {
                        $oneAheadParams = $filter->withPage($page + 1);
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($oneAheadParams); ?>"><?= $page+1; ?></a></li>
                        <?php
                    }

                    if ($page + 2 <= $totalPagesCount) {
                        $twoAheadParams = $filter->withPage($page + 2);
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($twoAheadParams); ?>"><?= $page+2; ?></a></li>
                        <?php
                    }

                    if ($page < $totalPagesCount - 2) {
                        $lastPageParams = $filter->withPage($totalPagesCount);
                        ?>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($lastPageParams); ?>"><?= $totalPagesCount; ?></a></li>
                        <?php
                    }

                    if ($page < $totalPagesCount) {
                        $nextParams = $filter->withPage($page + 1);
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($nextParams); ?>" aria-label="Next">&gt;</a></li>
                        <?php
                    }
                    ?>
                </ul>
            </nav>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
