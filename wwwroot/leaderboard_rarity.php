<?php
require_once 'classes/PlayerLeaderboardFilter.php';
require_once 'classes/PlayerRarityLeaderboardService.php';
require_once 'classes/PlayerLeaderboardPage.php';

$title = "PSN Rarity Leaderboard ~ PSN 100%";
require_once("header.php");

$playerLeaderboardFilter = PlayerLeaderboardFilter::fromArray($_GET ?? []);
$playerLeaderboardService = new PlayerRarityLeaderboardService($database);
$playerLeaderboardPage = new PlayerLeaderboardPage($playerLeaderboardService, $playerLeaderboardFilter);

$players = $playerLeaderboardPage->getPlayers();
$filterParameters = $playerLeaderboardPage->getFilterParameters();
$pageParameters = $playerLeaderboardPage->getPageQueryParameters($playerLeaderboardPage->getCurrentPage());
?>

<main class="container">
    <div class="row">
        <div class="col-12">
            <div class="hstack gap-3">
                <h1>PSN Rarity Leaderboard</h1>
                <div class="bg-body-tertiary p-3 rounded">
                    <div class="btn-group">
                        <a class="btn btn-outline-primary" href="/leaderboard/trophy?<?= http_build_query($filterParameters); ?>">Trophy</a>
                        <a class="btn btn-primary active" href="/leaderboard/rarity">Rarity</a>
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
                                if ($playerLeaderboardFilter->hasCountry()) {
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
                                <th scope="col" class="text-center">Legendary</th>
                                <th scope="col" class="text-center">Epic</th>
                                <th scope="col" class="text-center">Rare</th>
                                <th scope="col" class="text-center">Uncommon</th>
                                <th scope="col" class="text-center">Common</th>
                                <th scope="col" class="text-center">Points</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            foreach ($players as $player) {
                                $countryName = $utility->getCountryName($player["country"]);
                                if (isset($_GET["player"]) && $_GET["player"] == $player["online_id"]) {
                                    echo "<tr id=\"" . $player["online_id"] . "\" class=\"table-primary\">";
                                } else {
                                    echo "<tr id=\"" . $player["online_id"] . "\">";
                                }

                                $paramsAvatar = $filterParameters;
                                $paramsAvatar["avatar"] = $player["avatar_url"];
                                $paramsCountry = $filterParameters;
                                $paramsCountry["country"] = $player["country"];
                                ?>
                                <th scope="row" class="text-center align-middle">
                                    <?php
                                    if ($playerLeaderboardFilter->hasCountry()) {
                                        if ($player["rarity_rank_country_last_week"] == 0 || $player["rarity_rank_country_last_week"] == 16777215) {
                                            echo "New!";
                                            if ($player["trophy_count_npwr"] < $player["trophy_count_sony"]) {
                                                echo " <span style='color: #9d9d9d;'>(H)</span>";
                                            }
                                        } else {
                                            $delta = $player["rarity_rank_country_last_week"] - $player["ranking_country"];

                                            echo "<div class='vstack'>";
                                            if ($delta > 0) {
                                                echo "<span style='color: #0bd413; cursor: default;' title='+" . $delta . "'>&#9650;</span>";
                                            }

                                            echo $player["ranking_country"];
                                            if ($player["trophy_count_npwr"] < $player["trophy_count_sony"]) {
                                                echo " <span style='color: #9d9d9d;'>(H)</span>";
                                            }

                                            if ($delta < 0) {
                                                echo "<span style='color: #d40b0b; cursor: default;' title='" . $delta . "'>&#9660;</span>";
                                            }
                                            echo "</div>";
                                        }
                                    } else {
                                        if ($player["rarity_rank_last_week"] == 0 || $player["rarity_rank_last_week"] == 16777215) {
                                            echo "New!";
                                            if ($player["trophy_count_npwr"] < $player["trophy_count_sony"]) {
                                                echo " <span style='color: #9d9d9d;'>(H)</span>";
                                            }
                                        } else {
                                            $delta = $player["rarity_rank_last_week"] - $player["ranking"];

                                            echo "<div class='vstack'>";
                                            if ($delta > 0) {
                                                echo "<span style='color: #0bd413; cursor: default;' title='+" . $delta . "'>&#9650;</span>";
                                            }

                                            echo $player["ranking"];
                                            if ($player["trophy_count_npwr"] < $player["trophy_count_sony"]) {
                                                echo " <span style='color: #9d9d9d;'>(H)</span>";
                                            }

                                            if ($delta < 0) {
                                                echo "<span style='color: #d40b0b; cursor: default;' title='" . $delta . "'>&#9660;</span>";
                                            }
                                            echo "</div>";
                                        }
                                    }
                                    ?>
                                </th>
                                <td class="align-middle">
                                    <div class="hstack gap-3">
                                        <div>
                                            <a href="?<?= http_build_query($paramsAvatar); ?>">
                                                <img src="/img/avatar/<?= $player["avatar_url"]; ?>" alt="" height="50" width="50" />
                                            </a>
                                        </div>

                                        <div>
                                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/player/<?= $player["online_id"]; ?>"><?= $player["online_id"]; ?></a>
                                        </div>

                                        <div class="ms-auto">
                                            <a href="?<?= http_build_query($paramsCountry); ?>">
                                                <img src="/img/country/<?= $player["country"]; ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <img src="/img/star.svg" class="mb-1" alt="Level" title="Level" height="18" /> <?= number_format($player["level"]); ?>
                                    <div class="progress" title="<?= $player["progress"]; ?>%">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $player["progress"]; ?>%" aria-valuenow="<?= $player["progress"]; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </td>
                                <td class="text-center align-middle"><span class="trophy-legendary"><?= number_format($player["legendary"]); ?></span></td>
                                <td class="text-center align-middle"><span class="trophy-epic"><?= number_format($player["epic"]); ?></span></td>
                                <td class="text-center align-middle"><span class="trophy-rare"><?= number_format($player["rare"]); ?></span></td>
                                <td class="text-center align-middle"><span class="trophy-uncommon"><?= number_format($player["uncommon"]); ?></span></td>
                                <td class="text-center align-middle"><span class="trophy-common"><?= number_format($player["common"]); ?></span></td>
                                <td class="text-center align-middle"><?= number_format($player["rarity_points"]); ?></td>
                                <?php
                                echo "</tr>";
                            }
                            ?>
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
            <nav aria-label="Leaderboard page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    if ($playerLeaderboardPage->hasPreviousPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardPage->getPageQueryParameters($playerLeaderboardPage->getPreviousPage())); ?>">&lt;</a></li>
                        <?php
                    }

                    if ($playerLeaderboardPage->shouldShowFirstPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardPage->getPageQueryParameters($playerLeaderboardPage->getFirstPage())); ?>"><?= $playerLeaderboardPage->getFirstPage(); ?></a></li>
                        <?php
                    }

                    if ($playerLeaderboardPage->shouldShowLeadingEllipsis()) {
                        ?>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <?php
                    }

                    foreach ($playerLeaderboardPage->getPreviousPages() as $previousPage) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardPage->getPageQueryParameters($previousPage)); ?>"><?= $previousPage; ?></a></li>
                        <?php
                    }
                    ?>

                    <li class="page-item active" aria-current="page"><a class="page-link" href="?<?= http_build_query($pageParameters); ?>"><?= $playerLeaderboardPage->getCurrentPage(); ?></a></li>

                    <?php
                    foreach ($playerLeaderboardPage->getNextPages() as $nextPage) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardPage->getPageQueryParameters($nextPage)); ?>"><?= $nextPage; ?></a></li>
                        <?php
                    }

                    if ($playerLeaderboardPage->shouldShowTrailingEllipsis()) {
                        ?>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <?php
                    }

                    if ($playerLeaderboardPage->shouldShowLastPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardPage->getPageQueryParameters($playerLeaderboardPage->getLastPage())); ?>"><?= $playerLeaderboardPage->getLastPage(); ?></a></li>
                        <?php
                    }

                    if ($playerLeaderboardPage->hasNextPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardPage->getPageQueryParameters($playerLeaderboardPage->getNextPage())); ?>">&gt;</a></li>
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
