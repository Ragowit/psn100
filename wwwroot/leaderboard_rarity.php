<?php
$title = "PSN Rarity Leaderboard ~ PSN 100%";
require_once("header.php");

$url = $_SERVER["REQUEST_URI"];
$url_parts = parse_url($url);
// If URL doesn't have a query string.
if (isset($url_parts["query"])) { // Avoid 'Undefined index: query'
    parse_str($url_parts["query"], $params);
} else {
    $params = array();
}

$sql = "SELECT COUNT(*) FROM player WHERE `status` = 0";
if (!empty($_GET["country"])) {
    $sql .= " AND country = :country";
}
if (!empty($_GET["avatar"])) {
    $sql .= " AND avatar_url = :avatar";
}

$query = $database->prepare($sql);
if (!empty($_GET["country"])) {
    $country = $_GET["country"];
    $query->bindParam(":country", $country, PDO::PARAM_STR);
}
if (!empty($_GET["avatar"])) {
    $avatar = $_GET["avatar"];
    $query->bindParam(":avatar", $avatar, PDO::PARAM_STR);
}
$query->execute();
$total_pages = $query->fetchColumn();

$page = max(isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1, 1);
$limit = 50;
$offset = ($page - 1) * $limit;

$paramsWithoutPage = $params;
unset($paramsWithoutPage["page"]);
?>

<main class="container">
    <div class="row">
        <div class="col-12">
            <div class="hstack gap-3">
                <h1>PSN Rarity Leaderboard</h1>
                <div class="bg-body-tertiary p-3 rounded">
                    <div class="btn-group">
                        <a class="btn btn-outline-primary" href="/leaderboard/trophy?<?= http_build_query($paramsWithoutPage); ?>">Trophy</a>
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
                                if (isset($_GET["country"])) {
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
                            $sql = "SELECT * FROM (SELECT *, RANK() OVER (ORDER BY `rarity_points` DESC) `ranking` FROM `player` WHERE `status` = 0";
                            if (!empty($_GET["country"])) {
                                $sql .= " AND `country` = :country";
                            }
                            $sql .= ") p";
                            if (!empty($_GET["avatar"])) {
                                $sql .= " WHERE p.avatar_url = :avatar";
                            }
                            $sql .= " ORDER BY `ranking` LIMIT :offset, :limit";

                            $query = $database->prepare($sql);
                            if (!empty($_GET["country"])) {
                                $country = $_GET["country"];
                                $query->bindParam(":country", $country, PDO::PARAM_STR);
                            }
                            if (!empty($_GET["avatar"])) {
                                $avatar = $_GET["avatar"];
                                $query->bindParam(":avatar", $avatar, PDO::PARAM_STR);
                            }
                            $query->bindParam(":offset", $offset, PDO::PARAM_INT);
                            $query->bindParam(":limit", $limit, PDO::PARAM_INT);
                            $query->execute();
                            $players = $query->fetchAll();

                            foreach ($players as $player) {
                                $countryName = Locale::getDisplayRegion("-" . $player["country"], "en");
                                if (isset($_GET["player"]) && $_GET["player"] == $player["online_id"]) {
                                    echo "<tr id=\"". $player["online_id"] ."\" class=\"table-primary\">";
                                } else {
                                    echo "<tr id=\"". $player["online_id"] ."\">";
                                }

                                $paramsAvatar = $paramsWithoutPage;
                                $paramsAvatar["avatar"] = $player["avatar_url"];
                                $paramsCountry = $paramsWithoutPage;
                                $paramsCountry["country"] = $player["country"];
                                ?>
                                <th scope="row" class="text-center align-middle">
                                    <?php
                                    if (isset($_GET["country"])) {
                                        if ($player["rarity_rank_country_last_week"] == 0 || $player["rarity_rank_country_last_week"] == 16777215) {
                                            echo "New!";
                                        } else {
                                            $delta = $player["rarity_rank_country_last_week"] - $player["ranking"];

                                            echo "<div class='vstack'>";
                                            if ($delta > 0) {
                                                echo "<span style='color: #0bd413; cursor: default;' title='+". $delta ."'>&#9650;</span>";
                                            }
                                            
                                            echo $player["ranking"];

                                            if ($delta < 0) {
                                                echo "<span style='color: #d40b0b; cursor: default;' title='". $delta ."'>&#9660;</span>";
                                            } 
                                            echo "</div>";
                                        }
                                    } else {
                                        if ($player["rarity_rank_last_week"] == 0 || $player["rarity_rank_last_week"] == 16777215) {
                                            echo "New!";
                                        } else {
                                            $delta = $player["rarity_rank_last_week"] - $player["ranking"];
            
                                            echo "<div class='vstack'>";
                                            if ($delta > 0) {
                                                echo "<span style='color: #0bd413; cursor: default;' title='+". $delta ."'>&#9650;</span>";
                                            }
                                            
                                            echo $player["ranking"];

                                            if ($delta < 0) {
                                                echo "<span style='color: #d40b0b; cursor: default;' title='". $delta ."'>&#9660;</span>";
                                            } 
                                            echo "</div>";
                                        }
                                    }
                                    ?>
                                </th>
                                <td>
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
                <?= ($total_pages == 0 ? "0" : $offset + 1); ?>-<?= min($offset + $limit, $total_pages); ?> of <?= number_format($total_pages); ?>
            </p>
        </div>
        <div class="col-12">
            <nav aria-label="Leaderboard page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    if ($page > 1) {
                        $params["page"] = $page - 1; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">&lt;</a></li>
                        <?php
                    }

                    if ($page > 3) {
                        $params["page"] = 1; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">1</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
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
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= ceil($total_pages / $limit); ?></a></li>
                        <?php
                    }

                    if ($page < ceil($total_pages / $limit)) {
                        $params["page"] = $page + 1; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">&gt;</a></li>
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
