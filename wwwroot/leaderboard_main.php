<?php
require_once 'classes/PlayerLeaderboardFilter.php';
require_once 'classes/PlayerLeaderboardService.php';

$title = "PSN Trophy Leaderboard ~ PSN 100%";
require_once("header.php");

$playerLeaderboardFilter = PlayerLeaderboardFilter::fromArray($_GET);
$playerLeaderboardService = new PlayerLeaderboardService($database);

$limit = PlayerLeaderboardService::PAGE_SIZE;
$page = $playerLeaderboardFilter->getPage();
$offset = $playerLeaderboardFilter->getOffset($limit);

$totalPlayers = $playerLeaderboardService->countPlayers($playerLeaderboardFilter);
$totalPages = (int) ceil($totalPlayers / $limit);
$players = $playerLeaderboardService->getPlayers($playerLeaderboardFilter, $limit);

$filterParameters = $playerLeaderboardFilter->getFilterParameters();
$pageParameters = $playerLeaderboardFilter->toQueryParameters();
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
                                <th scope="col" class="text-center">Platinum</th>
                                <th scope="col" class="text-center">Gold</th>
                                <th scope="col" class="text-center">Silver</th>
                                <th scope="col" class="text-center">Bronze</th>
                                <th scope="col" class="text-center">Trophies</th>
                                <th scope="col" class="text-center">Points</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            foreach ($players as $player) {
                                $trophies = $player["bronze"] + $player["silver"] + $player["gold"] + $player["platinum"];
                                $countryName = $utility->getCountryName($player["country"]);
                                if (isset($_GET["player"]) && $_GET["player"] == $player["online_id"]) {
                                    echo "<tr id=\"". $player["online_id"] ."\" class=\"table-primary\">";
                                } else {
                                    echo "<tr id=\"". $player["online_id"] ."\">";
                                }

                                $paramsAvatar = $filterParameters;
                                $paramsAvatar["avatar"] = $player["avatar_url"];
                                $paramsCountry = $filterParameters;
                                $paramsCountry["country"] = $player["country"];
                                ?>
                                <th scope="row" class="text-center align-middle">
                                    <?php
                                    if ($playerLeaderboardFilter->hasCountry()) {
                                        if ($player["rank_country_last_week"] == 0 || $player["rank_country_last_week"] == 16777215) {
                                            echo "New!";
                                            if ($player["trophy_count_npwr"] < $player["trophy_count_sony"]) {
                                                echo " <span style='color: #9d9d9d;'>(H)</span>";
                                            }
                                        } else {
                                            $delta = $player["rank_country_last_week"] - $player["ranking_country"];

                                            echo "<div class='vstack'>";
                                            if ($delta > 0) {
                                                echo "<span style='color: #0bd413; cursor: default;' title='+". $delta ."'>&#9650;</span>";
                                            }
                                            
                                            echo $player["ranking_country"];
                                            if ($player["trophy_count_npwr"] < $player["trophy_count_sony"]) {
                                                echo " <span style='color: #9d9d9d;'>(H)</span>";
                                            }

                                            if ($delta < 0) {
                                                echo "<span style='color: #d40b0b; cursor: default;' title='". $delta ."'>&#9660;</span>";
                                            } 
                                            echo "</div>";
                                        }
                                    } else {
                                        if ($player["rank_last_week"] == 0 || $player["rank_last_week"] == 16777215) {
                                            echo "New!";
                                            if ($player["trophy_count_npwr"] < $player["trophy_count_sony"]) {
                                                echo " <span style='color: #9d9d9d;'>(H)</span>";
                                            }
                                        } else {
                                            $delta = $player["rank_last_week"] - $player["ranking"];
            
                                            echo "<div class='vstack'>";
                                            if ($delta > 0) {
                                                echo "<span style='color: #0bd413; cursor: default;' title='+". $delta ."'>&#9650;</span>";
                                            }
                                            
                                            echo $player["ranking"];
                                            if ($player["trophy_count_npwr"] < $player["trophy_count_sony"]) {
                                                echo " <span style='color: #9d9d9d;'>(H)</span>";
                                            }

                                            if ($delta < 0) {
                                                echo "<span style='color: #d40b0b; cursor: default;' title='". $delta ."'>&#9660;</span>";
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
                                <td class="text-center align-middle"><img src="/img/trophy-platinum.svg" alt="Platinum" height="18" /> <span class="trophy-platinum"><?= number_format($player["platinum"]); ?></span></td>
                                <td class="text-center align-middle"><img src="/img/trophy-gold.svg" alt="Gold" height="18" /> <span class="trophy-gold"><?= number_format($player["gold"]); ?></span></td>
                                <td class="text-center align-middle"><img src="/img/trophy-silver.svg" alt="Silver" height="18" /> <span class="trophy-silver"><?= number_format($player["silver"]); ?></span></td>
                                <td class="text-center align-middle"><img src="/img/trophy-bronze.svg" alt="Bronze" height="18" /> <span class="trophy-bronze"><?= number_format($player["bronze"]); ?></span></td>
                                <td class="text-center align-middle"><?= number_format($trophies); ?></td>
                                <td class="text-center align-middle"><?= number_format($player["points"]); ?></td>
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
                <?= ($totalPlayers == 0 ? "0" : $offset + 1); ?>-<?= min($offset + $limit, $totalPlayers); ?> of <?= number_format($totalPlayers); ?>
            </p>
        </div>
        <div class="col-12">
            <nav aria-label="Leaderboard page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    if ($page > 1) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardFilter->withPage($page - 1)); ?>">&lt;</a></li>
                        <?php
                    }

                    if ($page > 3) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardFilter->withPage(1)); ?>">1</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <?php
                    }

                    if ($page-2 > 0) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardFilter->withPage($page - 2)); ?>"><?= $page-2; ?></a></li>
                        <?php
                    }

                    if ($page-1 > 0) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardFilter->withPage($page - 1)); ?>"><?= $page-1; ?></a></li>
                        <?php
                    }
                    ?>

                    <li class="page-item active" aria-current="page"><a class="page-link" href="?<?= http_build_query($pageParameters); ?>"><?= $page; ?></a></li>

                    <?php
                    if ($page < $totalPages) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardFilter->withPage($page + 1)); ?>"><?= $page+1; ?></a></li>
                        <?php
                    }

                    if ($page + 1 < $totalPages) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardFilter->withPage($page + 2)); ?>"><?= $page+2; ?></a></li>
                        <?php
                    }

                    if ($page < $totalPages - 2) {
                        ?>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardFilter->withPage($totalPages)); ?>"><?= $totalPages; ?></a></li>
                        <?php
                    }

                    if ($page < $totalPages) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($playerLeaderboardFilter->withPage($page + 1)); ?>">&gt;</a></li>
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
