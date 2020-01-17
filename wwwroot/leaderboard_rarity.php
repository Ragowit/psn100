<?php
$title = "Rarity Leaderboard ~ PSN100.net";
require_once("header.php");

$url = $_SERVER["REQUEST_URI"];
$url_parts = parse_url($url);
// If URL doesn't have a query string.
if (isset($url_parts["query"])) { // Avoid 'Undefined index: query'
    parse_str($url_parts["query"], $params);
} else {
    $params = array();
}
?>
<main role="main">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1>Rarity Leaderboard</h1>
            </div>
        </div>

        <div class="row">
            <div class="col-2 text-center">
                <h5><a href="/leaderboard/main">Main</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5>Rarity</h5>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <nav aria-label="Page navigation">
                    <?php
                    if (isset($_GET["country"])) {
                        $country = $_GET["country"];

                        $query = $database->prepare("SELECT COUNT(*) FROM player WHERE rank != 0 AND country = :country");
                        $query->bindParam(":country", $country, PDO::PARAM_STR);
                    } else {
                        $query = $database->prepare("SELECT COUNT(*) FROM player WHERE rank != 0");
                    }
                    $query->execute();
                    $total_pages = $query->fetchColumn();

                    $page = isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1;
                    $limit = 50;

                    $offset = ($page - 1) * $limit;
                    ?>
                    <ul class="pagination justify-content-center">
                        <?php
                        if ($page > 1) {
                            $params["page"] = $page - 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">Prev</a></li>
                            <?php
                        }

                        if ($page > 3) {
                            $params["page"] = 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">1</a></li>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                            <?php
                        }

                        if ($page-2 > 0) {
                            $params["page"] = $page - 2; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page-2; ?></a></li>
                            <?php
                        }

                        if ($page-1 > 0) {
                            $params["page"] = $page - 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page-1; ?></a></li>
                            <?php
                        }
                        ?>

                        <?php
                        $params["page"] = $page;
                        ?>
                        <li class="page-item active" aria-current="page"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page; ?></a></li>

                        <?php
                        if ($page+1 < ceil($total_pages / $limit)+1) {
                            $params["page"] = $page + 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page+1; ?></a></li>
                            <?php
                        }

                        if ($page+2 < ceil($total_pages / $limit)+1) {
                            $params["page"] = $page + 2; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page+2; ?></a></li>
                            <?php
                        }

                        if ($page < ceil($total_pages / $limit)-2) {
                            $params["page"] = ceil($total_pages / $limit); ?>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= ceil($total_pages / $limit); ?></a></li>
                            <?php
                        }

                        if ($page < ceil($total_pages / $limit)) {
                            $params["page"] = $page + 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">Next</a></li>
                            <?php
                        }
                        ?>
                    </ul>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <table class="table table-responsive table-striped">
                    <tr>
                        <?php
                        if (isset($_GET["country"])) {
                            ?>
                            <th scope="col" class="align-middle">Country Rank</th>
                            <?php
                        } else {
                            ?>
                            <th scope="col" class="align-middle">Rank</th>
                            <?php
                        }
                        ?>
                        <th scope="col"></th>
                        <th scope="col" width="100%"></th>
                        <th scope="col"></th>
                        <th scope="col" class="text-center"><img src="/img/playstation/level.png" alt="Level" /></th>
                        <th scope="col" class="text-center">Common</th>
                        <th scope="col" class="text-center">Uncommon</th>
                        <th scope="col" class="text-center">Rare</th>
                        <th scope="col" class="text-center">Epic</th>
                        <th scope="col" class="text-center">Legendary</th>
                        <th scope="col" class="text-center align-middle">Rarity Points</th>
                        <th scope="col" class="text-center align-middle">Delta</th>
                    </tr>

                    <?php
                    if (isset($_GET["country"])) {
                        $country = $_GET["country"];

                        $query = $database->prepare("SELECT * FROM player WHERE rarity_rank != 0 AND country = :country ORDER BY rarity_rank LIMIT :offset, :limit");
                        $query->bindParam(":country", $country, PDO::PARAM_STR);
                    } else {
                        $query = $database->prepare("SELECT * FROM player WHERE rarity_rank != 0 ORDER BY rarity_rank LIMIT :offset, :limit");
                    }
                    $query->bindParam(":offset", $offset, PDO::PARAM_INT);
                    $query->bindParam(":limit", $limit, PDO::PARAM_INT);
                    $query->execute();
                    $players = $query->fetchAll();

                    foreach ($players as $player) {
                        $countryName = Locale::getDisplayRegion("-" . $player["country"], "en");

                        if (isset($_GET["player"]) && $_GET["player"] == $player["online_id"]) {
                            echo "<tr class=\"table-success\">";
                        } else {
                            echo "<tr>";
                        }

                        if (isset($_GET["country"])) {
                            ?>
                            <th scope="row" class="align-middle"><?= $player["rarity_rank_country"]; ?></th>
                            <?php
                        } else {
                            ?>
                            <th scope="row" class="align-middle"><?= $player["rarity_rank"]; ?></th>
                            <?php
                        } ?>
                            <td class="text-center">
                                <div style="position:relative;">
                                    <a href="/player/<?= $player["online_id"]; ?>">
                                        <img src="/img/avatar/<?= $player["avatar_url"]; ?>" alt="" height="50" width="50" />
                                        <?php
                                        if ($player["plus"] === "1") {
                                            ?>
                                            <img src="/img/playstation/plus.png" style="position:absolute; top:-5px; right:-5px; width:25px;" alt="" />
                                            <?php
                                        } ?>
                                    </a>
                                </div>
                            </td>
                            <td class="align-middle"><a href="/player/<?= $player["online_id"]; ?>"><?= $player["online_id"]; ?></a></td>
                            <td class="text-center">
                                <a href="/leaderboard/rarity?country=<?= $player["country"]; ?>">
                                    <img src="/img/country/<?= $player["country"]; ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
                                </a>
                            </td>
                            <td class="text-center">
                                <?= $player["level"]; ?>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $player["progress"]; ?>%" aria-valuenow="<?= $player["progress"]; ?>" aria-valuemin="0" aria-valuemax="100"><?= $player["progress"]; ?>%</div>
                                </div>
                            </td>
                            <td class="text-center"><?= $player["common"]; ?></td>
                            <td class="text-center"><?= $player["uncommon"]; ?></td>
                            <td class="text-center"><?= $player["rare"]; ?></td>
                            <td class="text-center"><?= $player["epic"]; ?></td>
                            <td class="text-center"><?= $player["legendary"]; ?></td>
                            <td class="text-center"><?= $player["rarity_points"]; ?></td>
                            <td class="text-center">
                                <?php
                                if (isset($_GET["country"])) {
                                    $rank = "rarity_rank_country";
                                    $rankLastWeek = "rarity_rank_country_last_week";
                                } else {
                                    $rank = "rarity_rank";
                                    $rankLastWeek = "rarity_rank_last_week";
                                }

                        if ($player[$rankLastWeek] == 0) {
                            echo "New!";
                        } else {
                            $delta = $player[$rankLastWeek] - $player[$rank];

                            if ($delta < 0) {
                                echo $delta;
                            } elseif ($delta > 0) {
                                echo "+". $delta;
                            } else {
                                echo "-";
                            }
                        } ?>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </table>
            </div>
        </div>
    </div>
</main>
<?php
require_once("footer.php");
?>
