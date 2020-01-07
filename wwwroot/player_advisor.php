<?php
if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

$query = $database->prepare("SELECT * FROM player WHERE account_id = :account_id");
$query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
$query->execute();
$player = $query->fetch();

$title = $player["online_id"] . "'s Trophy Advisor ~ PSN100.net";
require_once("header.php");

$aboutMe = htmlentities($player["about_me"], ENT_QUOTES, 'UTF-8');
$countryName = Locale::getDisplayRegion("-" . $player["country"], 'en');
$trophies = $player["bronze"] + $player["silver"] + $player["gold"] + $player["platinum"];
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
                <h5><a href="/player/<?= $player["online_id"]; ?>/log">Log</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5>Trophy Advisor</h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/game?sort=completion&player=<?= $player["online_id"]; ?>">Game Advisor</a></h5>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <nav aria-label="Page navigation">
                    <?php
                    $total_pages = $unearnedTrophies;

                    $page = isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1;
                    $limit = 50;

                    $offset = ($page - 1) * $limit;
                    ?>
                    <ul class="pagination justify-content-center">
                        <?php
                        if ($page > 1) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page-1; ?>">Prev</a></li>
                            <?php
                        }

                        if ($page > 3) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                            <?php
                        }

                        if ($page-2 > 0) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page-2; ?>"><?= $page-2; ?></a></li>
                            <?php
                        }

                        if ($page-1 > 0) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page-1; ?>"><?= $page-1; ?></a></li>
                            <?php
                        }
                        ?>

                        <li class="page-item active" aria-current="page"><a class="page-link" href="?page=<?= $page; ?>"><?= $page; ?></a></li>

                        <?php
                        if ($page+1 < ceil($total_pages / $limit)+1) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page+1; ?>"><?= $page+1; ?></a></li>
                            <?php
                        }

                        if ($page+2 < ceil($total_pages / $limit)+1) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page+2; ?>"><?= $page+2; ?></a></li>
                            <?php
                        }

                        if ($page < ceil($total_pages / $limit)-2) {
                            ?>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                            <li class="page-item"><a class="page-link" href="?page=<?= ceil($total_pages / $limit); ?>"><?= ceil($total_pages / $limit); ?></a></li>
                            <?php
                        }

                        if ($page < ceil($total_pages / $limit)) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page+1; ?>">Next</a></li>
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
                        <th scope="col">Rarity</th>
                        <th scope="col">Type</th>
                    </tr>

                    <?php
                    $query = $database->prepare("SELECT tt.id AS game_id, tt.name AS game_name, tg.icon_url AS group_icon_url, tg.name AS group_name, t.id AS trophy_id, t.icon_url AS trophy_icon_url, t.name AS trophy_name, t.detail AS trophy_detail, t.rarity_percent, t.type FROM trophy t JOIN trophy_title tt USING (np_communication_id) JOIN trophy_group tg USING (np_communication_id, group_id) LEFT JOIN trophy_earned te ON t.np_communication_id = te.np_communication_id AND t.group_id = te.group_id AND t.order_id = te.order_id AND te.account_id = :account_id JOIN trophy_title_player ttp ON t.np_communication_id = ttp.np_communication_id AND ttp.account_id = :account_id WHERE te.id IS NULL AND tt.status = 0 AND t.status = 0 ORDER BY rarity_percent DESC LIMIT :offset, :limit");
                    $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                    $query->bindParam(":offset", $offset, PDO::PARAM_INT);
                    $query->bindParam(":limit", $limit, PDO::PARAM_INT);
                    $query->execute();
                    $trophies = $query->fetchAll();

                    if (count($trophies) === 0) {
                        ?>
                        <tr>
                            <td colspan="5" class="text-center"><h3>This player seems to have a private profile.</h3></td>
                        </tr>
                        <?php
                    } else {
                        foreach ($trophies as $trophy) {
                            ?>
                            <tr>
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
                                <td class="text-center">
                                    <?= $trophy["rarity_percent"]; ?>%<br>
                                    <?php
                                    if ($trophy["rarity_percent"] <= 1.00) {
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
