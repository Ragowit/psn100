<?php
if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

$query = $database->prepare("SELECT * FROM player WHERE account_id = :account_id");
$query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
$query->execute();
$player = $query->fetch();

$title = $player["online_id"] . "'s Trophy Log ~ PSN100.net";
require_once("header.php");

$aboutMe = htmlentities($player["about_me"], ENT_QUOTES, 'UTF-8');
$countryName = Locale::getDisplayRegion("-" . $player["country"], 'en');
$trophies = $player["bronze"] + $player["silver"] + $player["gold"] + $player["platinum"];

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
            <?php
            if ($player["status"] == 1) {
                ?>
                <div class="col-12">
                    <div class="alert alert-warning" role="alert">
                        This player have some funny looking trophy data. This doesn't necessarily means cheating, but all data from this player will not be in any of the site statistics or leaderboards.
                    </div>
                </div>
                <?php
            }
            ?>

            <div class="col-2">
                <div style="position:relative;">
                    <img src="/img/avatar/<?= $player["avatar_url"]; ?>" alt="" height="160" width="160" />
                    <?php
                    if ($player["plus"] === "1") {
                        ?>
                        <img src="/img/playstation/plus.png" style="position:absolute; top:-5px; right:-5px; width:50px;" alt="" />
                        <?php
                    }
                    ?>
                </div>
            </div>
            <div class="col-8">
                <h1><?= $player["online_id"] ?></h1>
                <p><?= $aboutMe ?></p>
            </div>
            <div class="col-2 text-right">
                <img src="/img/country/<?= $player["country"]; ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName ?>" height="50" width="50" style="border-radius: 50%;" />
                <br>
                <small><?= str_replace(" ", "<br>", $player["last_updated_date"]); ?></small>
            </div>
        </div>

        <div class="row">
            <div class="col-2 text-center">
                <img src="/img/playstation/level.png" alt="Level" width="24" /> <?= $player["level"]; ?>
                <div class="progress">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $player["progress"]; ?>%" aria-valuenow="<?= $player["progress"]; ?>" aria-valuemin="0" aria-valuemax="100"><?= $player["progress"]; ?>%</div>
                </div>
            </div>
            <div class="col-2 text-center">
                <img src="/img/playstation/bronze.png" alt="Bronze" width="24" /> <?= $player["bronze"]; ?>
            </div>
            <div class="col-2 text-center">
                <img src="/img/playstation/silver.png" alt="Silver" width="24" /> <?= $player["silver"]; ?>
            </div>
            <div class="col-2 text-center">
                <img src="/img/playstation/gold.png" alt="Gold" width="24" /> <?= $player["gold"]; ?>
            </div>
            <div class="col-2 text-center">
                <img src="/img/playstation/platinum.png" alt="Platinum" width="24" /> <?= $player["platinum"]; ?>
            </div>
            <div class="col-2 text-center">
                <img src="/img/playstation/trophies.png" alt="Trophies" width="24" /> <?= $trophies; ?>
            </div>
        </div>

        <?php
        $query = $database->prepare("SELECT COUNT(*) FROM trophy_title_player ttp JOIN trophy_title tt USING (np_communication_id) WHERE tt.status = 0 AND ttp.account_id = :account_id");
        $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
        $query->execute();
        $numberOfGames = $query->fetchColumn();

        $query = $database->prepare("SELECT COUNT(*) FROM trophy_title_player ttp JOIN trophy_title tt USING (np_communication_id) WHERE tt.status = 0 AND ttp.progress = 100 AND ttp.account_id = :account_id");
        $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
        $query->execute();
        $numberOfCompletedGames = $query->fetchColumn();

        $query = $database->prepare("SELECT ROUND(AVG(ttp.progress), 2) FROM trophy_title_player ttp JOIN trophy_title tt USING (np_communication_id) WHERE tt.status = 0 AND ttp.account_id = :account_id");
        $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
        $query->execute();
        $averageProgress = $query->fetchColumn();

        $query = $database->prepare("SELECT SUM(tg.bronze-tgp.bronze + tg.silver-tgp.silver + tg.gold-tgp.gold + tg.platinum-tgp.platinum) FROM trophy_group_player tgp JOIN trophy_group tg USING (np_communication_id, group_id) JOIN trophy_title tt USING (np_communication_id) WHERE tt.status = 0 AND tgp.account_id = :account_id");
        $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
        $query->execute();
        $unearnedTrophies = $query->fetchColumn();
        ?>
        <div class="row">
            <div class="col-2 text-center">
                <h5><?= $numberOfGames; ?></h5>
                Games
            </div>
            <div class="col-2 text-center">
                <h5><?= $numberOfCompletedGames; ?></h5>
                100%
            </div>
            <div class="col-2 text-center">
                <h5><?= $averageProgress; ?>%</h5>
                Avg. Progress
            </div>
            <div class="col-2 text-center">
                <h5><?= $unearnedTrophies; ?></h5>
                Unearned Trophies
            </div>
            <div class="col-2 text-center">
                <?php
                if ($player["rank_last_week"] == 0) {
                    $rankTitle = "New!";
                } else {
                    $delta = $player["rank_last_week"] - $player["rank"];

                    if ($delta < 0) {
                        $rankTitle = $delta;
                    } elseif ($delta > 0) {
                        $rankTitle = "+". $delta;
                    } else {
                        $rankTitle = "Unchanged";
                    }
                }

                if ($player["rarity_rank_last_week"] == 0) {
                    $rarityRankTitle = "New!";
                } else {
                    $delta = $player["rarity_rank_last_week"] - $player["rarity_rank"];

                    if ($delta < 0) {
                        $rarityRankTitle = $delta;
                    } elseif ($delta > 0) {
                        $rarityRankTitle = "+". $delta;
                    } else {
                        $rarityRankTitle = "Unchanged";
                    }
                }

                if ($player["status"] == 0) {
                    ?>
                    <h5><span title="<?= $rankTitle; ?> on main leaderboard since last week."><?= $player["rank"]; ?></span> ~ <span title="<?= $rarityRankTitle; ?> on rarity leaderboard since last week."><?= $player["rarity_rank"]; ?></span></h5>
                    <?php
                } else {
                    ?>
                    <h5>N/A</h5>
                    <?php
                }
                ?>
                World Rank
            </div>
            <div class="col-2 text-center">
                <?php
                if ($player["rank_country_last_week"] == 0) {
                    $rankCountryTitle = "New!";
                } else {
                    $delta = $player["rank_country_last_week"] - $player["rank_country"];

                    if ($delta < 0) {
                        $rankCountryTitle = $delta;
                    } elseif ($delta > 0) {
                        $rankCountryTitle = "+". $delta;
                    } else {
                        $rankCountryTitle = "Unchanged";
                    }
                }

                if ($player["rarity_rank_country_last_week"] == 0) {
                    $rarityRankCountryTitle = "New!";
                } else {
                    $delta = $player["rarity_rank_country_last_week"] - $player["rarity_rank_country"];

                    if ($delta < 0) {
                        $rarityRankCountryTitle = $delta;
                    } elseif ($delta > 0) {
                        $rarityRankCountryTitle = "+". $delta;
                    } else {
                        $rarityRankCountryTitle = "Unchanged";
                    }
                }

                if ($player["status"] == 0) {
                    ?>
                    <h5><span title="<?= $rankCountryTitle; ?> on main country leaderboard since last week."><?= $player["rank_country"]; ?></span> ~ <span title="<?= $rarityRankCountryTitle; ?> on rarity country leaderboard since last week."><?= $player["rarity_rank_country"]; ?></span></h5>
                    <?php
                } else {
                    ?>
                    <h5>N/A</h5>
                    <?php
                }
                ?>
                Country Rank
            </div>
        </div>

        <div class="row">
            <div class="col-2 text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>">Games</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5>Log</h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>/advisor">Trophy Advisor</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/game?sort=completion&player=<?= $player["online_id"]; ?>">Game Advisor</a></h5>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <nav aria-label="Page navigation">
                    <?php
                    $query = $database->prepare("SELECT COUNT(*) FROM trophy_earned te WHERE te.account_id = :account_id");
                    $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
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
            <div class="col-12">
                <table class="table table-responsive table-striped">
                    <tr class="table-primary">
                        <th scope="col">Game Icon</th>
                        <th scope="col">Trophy Icon</th>
                        <th scope="col" width="100%">Description</th>
                        <th scope="col">Earned</th>
                        <th scope="col"><a href="?sort=rarity">Rarity</a></th>
                        <th scope="col">Type</th>
                    </tr>

                    <?php
                    if (isset($_GET["sort"])) {
                        $query = $database->prepare("SELECT te.*, tg.name AS group_name, tg.icon_url AS group_icon_url, t.id AS trophy_id, t.type, t.name AS trophy_name, t.detail AS trophy_detail, t.icon_url AS trophy_icon_url, t.rarity_percent, t.status AS trophy_status, tt.id AS game_id, tt.name AS game_name, tt.status AS game_status FROM trophy_earned te LEFT JOIN trophy_group tg ON tg.np_communication_id = te.np_communication_id AND tg.group_id = te.group_id LEFT JOIN trophy t ON t.np_communication_id = te.np_communication_id AND t.group_id = te.group_id and t.order_Id = te.order_id LEFT JOIN trophy_title tt ON tt.np_communication_id = te.np_communication_id WHERE te.account_id = :account_id ORDER BY t.rarity_percent LIMIT :offset, :limit");
                    } else {
                        $query = $database->prepare("SELECT te.*, tg.name AS group_name, tg.icon_url AS group_icon_url, t.id AS trophy_id, t.type, t.name AS trophy_name, t.detail AS trophy_detail, t.icon_url AS trophy_icon_url, t.rarity_percent, t.status AS trophy_status, tt.id AS game_id, tt.name AS game_name, tt.status AS game_status FROM trophy_earned te LEFT JOIN trophy_group tg ON tg.np_communication_id = te.np_communication_id AND tg.group_id = te.group_id LEFT JOIN trophy t ON t.np_communication_id = te.np_communication_id AND t.group_id = te.group_id and t.order_Id = te.order_id LEFT JOIN trophy_title tt ON tt.np_communication_id = te.np_communication_id WHERE te.account_id = :account_id ORDER BY te.earned_date DESC LIMIT :offset, :limit");
                    }
                    $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                    $query->bindParam(":offset", $offset, PDO::PARAM_INT);
                    $query->bindParam(":limit", $limit, PDO::PARAM_INT);
                    $query->execute();
                    $trophies = $query->fetchAll();

                    if (count($trophies) === 0) {
                        ?>
                        <tr>
                            <td colspan="6" class="text-center"><h3>This player seems to have a private profile.</h3></td>
                        </tr>
                        <?php
                    } else {
                        foreach ($trophies as $trophy) {
                            if ($trophy["game_status"] == 1) {
                                echo "<tr class=\"table-warning\" title=\"This game is delisted and the trophy will not be accounted for on any leaderboard.\">";
                            } elseif ($trophy["trophy_status"] == 1) {
                                echo "<tr class=\"table-warning\" title=\"This trophy is unobtainable and not accounted for on any leaderboard.\">";
                            } else {
                                echo "<tr>";
                            } ?>
                                <td>
                                    <a href="/game/<?= $trophy["game_id"] . "-" . str_replace(" ", "-", $trophy["game_name"]); ?>/<?= $player["online_id"]; ?>">
                                        <img src="/img/group/<?= $trophy["group_icon_url"]; ?>" alt="<?= $trophy["group_name"]; ?>" title="<?= $trophy["group_name"]; ?>" width="100" />
                                    </a>
                                </td>
                                <td>
                                    <img src="/img/trophy/<?= $trophy["trophy_icon_url"]; ?>" alt="<?= $trophy["trophy_name"]; ?>" title="<?= $trophy["trophy_name"]; ?>" width="44" />
                                </td>
                                <td style="width: 100%;">
                                    <a href="/trophy/<?= $trophy["trophy_id"] . "-" . str_replace(" ", "-", $trophy["trophy_name"]); ?>/<?= $player["online_id"]; ?>">
                                        <b><?= $trophy["trophy_name"]; ?></b>
                                    </a>
                                    <br>
                                    <?= $trophy["trophy_detail"]; ?>
                                </td>
                                <td class="text-center" style="white-space: nowrap;">
                                    <?= str_replace(" ", "<br>", $trophy["earned_date"]); ?>
                                </td>
                                <td class="text-center">
                                    <?= $trophy["rarity_percent"]; ?>%<br>
                                    <?php
                                    if ($trophy["trophy_status"] == 1) {
                                        echo "Unobtainable";
                                    } elseif ($trophy["rarity_percent"] <= 1.00) {
                                        echo "Legendary";
                                    } elseif ($trophy["rarity_percent"] <= 5.00) {
                                        echo "Epic";
                                    } elseif ($trophy["rarity_percent"] <= 20.00) {
                                        echo "Rare";
                                    } elseif ($trophy["rarity_percent"] <= 50.00) {
                                        echo "Uncommon";
                                    } else {
                                        echo "Common";
                                    } ?>
                                </td>
                                <td>
                                    <img src="/img/playstation/<?= $trophy["type"]; ?>.png" alt="<?= ucfirst($trophy["type"]); ?>" title="<?= ucfirst($trophy["type"]); ?>" />
                                </td>
                            </tr>
                            <?php
                        }
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
