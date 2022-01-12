<?php
if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

$query = $database->prepare("SELECT * FROM player WHERE account_id = :account_id");
$query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
$query->execute();
$player = $query->fetch();

require_once("header.php");

$aboutMe = nl2br(htmlentities($player["about_me"], ENT_QUOTES, 'UTF-8'));
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
                        This player have some funny looking trophy data. This doesn't necessarily means cheating, but all data from this player will not be in any of the site statistics or leaderboards. <a href="https://github.com/Ragowit/psn100/issues?q=label%3Acheater+<?= $player["online_id"]; ?>+OR+<?= $player["account_id"]; ?>">Dispute</a>?
                    </div>
                </div>
                <?php
            } elseif ($player["status"] == 2) {
                ?>
                <div class="col-12">
                    <div class="alert alert-warning" role="alert">
                        This player have <a href="https://www.playstation.com/en-us/support/games/hide-games-playstation-library/">hidden some of their games</a>. All data from this player will not be in any of the site statistics or leaderboards. Make sure this player have no longer any hidden trophies, and then issue a new scan of the profile on the front page.
                    </div>
                </div>
                <?php
            } elseif ($player["rank"] > 50000) {
                ?>
                <div class="col-12">
                    <div class="alert alert-warning" role="alert">
                        This player isn't ranked within the top 50000 and will not have its trophies contributed to the site statistics.
                        <?php
                        if ($player["rank"] > 100000) {
                            ?>
                            <br>
                            This player isn't ranked within the top 100000 and will not be included in the automatic player scanning routine.
                            <?php
                        }
                        ?>
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
                <p class="overflow-auto" style="height: 5rem;"><?= $aboutMe ?></p>
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
                <img src="/img/playstation/bronze.png" alt="Bronze" width="24" /> <?= number_format($player["bronze"]); ?>
            </div>
            <div class="col-2 text-center">
                <img src="/img/playstation/silver.png" alt="Silver" width="24" /> <?= number_format($player["silver"]); ?>
            </div>
            <div class="col-2 text-center">
                <img src="/img/playstation/gold.png" alt="Gold" width="24" /> <?= number_format($player["gold"]); ?>
            </div>
            <div class="col-2 text-center">
                <img src="/img/playstation/platinum.png" alt="Platinum" width="24" /> <?= number_format($player["platinum"]); ?>
            </div>
            <div class="col-2 text-center">
                <img src="/img/playstation/trophies.png" alt="Trophies" width="24" /> <?= number_format($trophies); ?>
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

        $query = $database->prepare("SELECT
                SUM(
                    tt.bronze - ttp.bronze + tt.silver - ttp.silver + tt.gold - ttp.gold + tt.platinum - ttp.platinum
                )
            FROM
                trophy_title_player ttp
            JOIN trophy_title tt USING(np_communication_id)
            WHERE
                tt.status = 0 AND ttp.account_id = :account_id");
        $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
        $query->execute();
        $unearnedTrophies = $query->fetchColumn();
        ?>
        <div class="row">
            <div class="col-2 text-center">
                <h5><?= number_format($numberOfGames); ?></h5>
                Games
            </div>
            <div class="col-2 text-center">
                <h5><?= number_format($numberOfCompletedGames); ?></h5>
                100%
            </div>
            <div class="col-2 text-center">
                <h5><?= $averageProgress; ?>%</h5>
                Avg. Progress
            </div>
            <div class="col-2 text-center">
                <h5><?= number_format($unearnedTrophies ?? 0); ?></h5>
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
                        $rankTitle = "=";
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
                        $rarityRankTitle = "=";
                    }
                }

                if ($player["status"] == 0) {
                    ?>
                    <h5>
                        <a href="/leaderboard/main?page=<?= ceil($player["rank"] / 50); ?>&player=<?= $player["online_id"]; ?>"><?= $player["rank"]; ?> (<?= $rankTitle; ?>)</a>
                        <br>
                        <a href="/leaderboard/rarity?page=<?= ceil($player["rarity_rank"] / 50); ?>&player=<?= $player["online_id"]; ?>"><?= $player["rarity_rank"]; ?> (<?= $rarityRankTitle; ?>)</a>
                    </h5>
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
                        $rankCountryTitle = "=";
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
                        $rarityRankCountryTitle = "=";
                    }
                }

                if ($player["status"] == 0) {
                    ?>
                    <h5>
                        <a href="/leaderboard/main?country=<?= $player["country"]; ?>&page=<?= ceil($player["rank_country"] / 50); ?>&player=<?= $player["online_id"]; ?>"><?= $player["rank_country"]; ?> (<?= $rankCountryTitle; ?>)</a>
                        <br>
                        <a href="/leaderboard/rarity?country=<?= $player["country"]; ?>&page=<?= ceil($player["rarity_rank_country"] / 50); ?>&player=<?= $player["online_id"]; ?>"><?= $player["rarity_rank_country"]; ?> (<?= $rarityRankCountryTitle; ?>)</a>
                    </h5>
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
