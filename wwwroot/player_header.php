<?php
$aboutMe = nl2br(htmlentities($player["about_me"], ENT_QUOTES, 'UTF-8'));
$countryName = Locale::getDisplayRegion("-" . $player["country"], 'en');
$trophies = $player["bronze"] + $player["silver"] + $player["gold"] + $player["platinum"];
?>

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
    } elseif ($player["status"] == 3) {
        ?>
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                This player seems to have a <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.playstation.com/en-us/support/account/privacy-settings-psn/">private</a> profile. Make sure this player is no longer private, and then issue a new scan of the profile on the front page.
            </div>
        </div>
        <?php
    } elseif ($player["status"] == 4) {
        ?>
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                This player have not played a game over a year and is considered inactive by this site. All data from this player will not be in any of the site statistics or leaderboards.
            </div>
        </div>
        <?php
    } elseif ($player["rank"] == 16777215) {
        ?>
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                This is a new player currently being scanned for the first time. Rank and stats will be done once the scan is complete.
            </div>
        </div>
        <?php
    } elseif ($player["rank"] > 50000) {
        ?>
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                This player isn't ranked within the top 50000 and will not have its trophies contributed to the site statistics.
            </div>
        </div>
        <?php
    }
    ?>
</div>

<div class="row">
    <div class="col-12 col-lg-4 mb-3">
        <div class="vstack gap-1 bg-body-tertiary p-3 rounded">
            <div>
                <h1 title="<?= $aboutMe; ?>"><?= $player["online_id"] ?></h1>
            </div>

            <div class="hstack gap-3">
                <div>
                    <img src="/img/avatar/<?= $player["avatar_url"]; ?>" alt="" height="100" width="100" />
                </div>

                <div class="vstack gap-1">
                    <div class="hstack">
                        <div class="vstack">
                            <?php
                            if ($player["status"] == 1 || $player["status"] == 3) {
                                echo "N/A";
                            } else {
                                ?>
                                <div class="w-75 text-center">
                                <!--  Level -->
                                    <img src="/img/star.svg" class="mb-1" alt="Level" title="Level" height="18" /> <?= $player["level"]; ?>
                                </div>

                                <div class="w-75">
                                    <!-- Progress -->
                                    <div class="progress" title="<?= $player["progress"]; ?>%">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $player["progress"]; ?>%" aria-valuenow="<?= $player["progress"]; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>

                        <div class="ms-auto">
                            <img src="/img/country/<?= $player["country"]; ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName ?>" height="50" width="50" style="border-radius: 50%;" />
                        </div>
                    </div>

                    <div>
                        <small>Last Updated: <span id="lastUpdate"></span></small>
                        <?php
                        if (!is_null($player["last_updated_date"])) {
                            ?>
                            <script>
                                document.getElementById("lastUpdate").innerHTML = new Date('<?= $player["last_updated_date"]; ?> UTC').toLocaleString('sv-SE');
                            </script>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="text-center bg-dark-subtle p-3 rounded">
                <?php
                if ($player["status"] == 1 || $player["status"] == 3) {
                    echo "N/A";
                } else {
                    ?>
                    <?= number_format($trophies); ?> Trophies<br>
                    <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= number_format($player["platinum"]); ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= number_format($player["gold"]); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= number_format($player["silver"]); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= number_format($player["bronze"]); ?></span>
                    <?php
                }
                ?>
            </div>
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
    ?>

    <div class="col-12 col-lg-4 mb-3">
        <div class="vstack gap-3 h-100 text-center">
            <div class="hstack gap-3">
                <div class="bg-body-tertiary p-3 rounded w-50">
                    Games
                    <hr class="m-2">
                    <?php
                    if ($player["status"] == 1 || $player["status"] == 3) {
                        echo "N/A";
                    } else {
                        ?>
                        <h2><?= number_format($numberOfGames); ?></h2>
                        <?php
                    }
                    ?>
                </div>

                <div class="bg-body-tertiary p-3 rounded w-50">
                    100% Completion
                    <hr class="m-2">
                    <?php
                    if ($player["status"] == 1 || $player["status"] == 3) {
                        echo "N/A";
                    } else {
                        ?>
                        <h2><?= number_format($numberOfCompletedGames); ?></h2>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <!-- Main Leaderboard -->
            <div class="bg-body-tertiary p-3 rounded w-100 mt-auto">
                <div class="vstack">
                    <div>
                        Main Leaderboard
                    </div>

                    <div>
                        <hr class="m-2">
                    </div>

                    <div class="hstack gap-3">
                        <div class="w-50">
                            World Rank<br>
                            <?php
                            // World Rank
                            if ($player["status"] == 0) {
                                ?>
                                <h3>
                                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/leaderboard/main?page=<?= ceil($player["rank"] / 50); ?>&player=<?= $player["online_id"]; ?>#<?= $player["online_id"]; ?>"><?= $player["rank"]; ?></a>
                                    <?php
                                    if ($player["rank_last_week"] == 0 || $player["rank_last_week"] == 16777215) {
                                        echo "<span class='fs-6'>(New!)</span>";
                                    } else {
                                        $delta = $player["rank_last_week"] - $player["rank"];

                                        if ($delta < 0) {
                                            echo "<span class='fs-6' style='color: #d40b0b;'>(". $delta .")</span>";
                                        } elseif ($delta > 0) {
                                            echo "<span class='fs-6' style='color: #0bd413;'>(+". $delta .")</span>";
                                        } else {
                                            echo "<span class='fs-6' style='color: #0070d1;'>(=)</span>";
                                        }
                                    }
                                    ?>
                                </h3>
                                <?php
                            } else {
                                ?>
                                <h3>N/A</h3>
                                <?php
                            }
                            ?>
                        </div>

                        <div class="vr">
                        </div>

                        <div class="w-50">
                            Country Rank<br>
                            <?php
                            // Country Rank
                            if ($player["status"] == 0) {
                                ?>
                                <h3>
                                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/leaderboard/main?country=<?= $player["country"]; ?>&page=<?= ceil($player["rank_country"] / 50); ?>&player=<?= $player["online_id"]; ?>#<?= $player["online_id"]; ?>"><?= $player["rank_country"]; ?></a>
                                    <?php
                                    if ($player["rank_country_last_week"] == 0 || $player["rank_country_last_week"] == 16777215) {
                                        echo "<span class='fs-6'>(New!)</span>";
                                    } else {
                                        $delta = $player["rank_country_last_week"] - $player["rank_country"];

                                        if ($delta < 0) {
                                            echo "<span class='fs-6' style='color: #d40b0b;'>(". $delta .")</span>";
                                        } elseif ($delta > 0) {
                                            echo "<span class='fs-6' style='color: #0bd413;'>(+". $delta .")</span>";
                                        } else {
                                            echo "<span class='fs-6' style='color: #0070d1;'>(=)</span>";
                                        }
                                    }
                                    ?>
                                </h3>
                                <?php
                            } else {
                                ?>
                                <h3>N/A</h3>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
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

    <div class="col-12 col-lg-4 mb-3">
        <div class="vstack gap-3 text-center h-100">
            <div class="hstack gap-3">
                <div class="bg-body-tertiary p-3 rounded w-50">
                    Average Progress
                    <hr class="m-2">
                    <?php
                    if ($player["status"] == 1 || $player["status"] == 3) {
                        echo "N/A";
                    } else {
                        ?>
                        <h2><?= $averageProgress; ?>%</h2>
                        <?php
                    }
                    ?>
                </div>

                <div class="bg-body-tertiary p-3 rounded w-50">
                    Unearned Trophies
                    <hr class="m-2">
                    <?php
                    if ($player["status"] == 1 || $player["status"] == 3) {
                        echo "N/A";
                    } else {
                        ?>
                        <h2><?= number_format($unearnedTrophies ?? 0); ?></h2>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <!-- Rarity Leaderboard -->
            <div class="bg-body-tertiary p-3 rounded w-100 mt-auto">
                <div class="vstack">
                    <div>
                        Rarity Leaderboard
                    </div>

                    <div>
                        <hr class="m-2">
                    </div>

                    <div class="hstack gap-3">
                        <div class="w-50">
                            World Rank<br>
                            <?php
                            // World Rank
                            if ($player["status"] == 0) {
                                ?>
                                <h3>
                                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/leaderboard/rarity?page=<?= ceil($player["rarity_rank"] / 50); ?>&player=<?= $player["online_id"]; ?>#<?= $player["online_id"]; ?>"><?= $player["rarity_rank"]; ?></a>
                                    <?php
                                    if ($player["rarity_rank_last_week"] == 0 || $player["rarity_rank_last_week"] == 16777215) {
                                        echo "<span class='fs-6'>(New!)</span>";
                                    } else {
                                        $delta = $player["rarity_rank_last_week"] - $player["rarity_rank"];

                                        if ($delta < 0) {
                                            echo "<span class='fs-6' style='color: #d40b0b;'>(". $delta .")</span>";
                                        } elseif ($delta > 0) {
                                            echo "<span class='fs-6' style='color: #0bd413;'>(+". $delta .")</span>";
                                        } else {
                                            echo "<span class='fs-6' style='color: #0070d1;'>(=)</span>";
                                        }    
                                    }
                                    ?>
                                </h3>
                                <?php
                            } else {
                                ?>
                                <h3>N/A</h3>
                                <?php
                            }
                            ?>
                        </div>

                        <div class="vr">
                        </div>

                        <div class="w-50">
                            Country Rank<br>
                            <?php
                            // Country Rank
                            if ($player["status"] == 0) {
                                ?>
                                <h3>
                                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/leaderboard/rarity?country=<?= $player["country"]; ?>&page=<?= ceil($player["rarity_rank_country"] / 50); ?>&player=<?= $player["online_id"]; ?>#<?= $player["online_id"]; ?>"><?= $player["rarity_rank_country"]; ?></a>
                                    <?php
                                    if ($player["rarity_rank_country_last_week"] == 0 || $player["rarity_rank_country_last_week"] == 16777215) {
                                        echo "<span class='fs-6'>(New!)</span>";
                                    } else {
                                        $delta = $player["rarity_rank_country_last_week"] - $player["rarity_rank_country"];

                                        if ($delta < 0) {
                                            echo "<span class='fs-6' style='color: #d40b0b;'>(". $delta .")</span>";
                                        } elseif ($delta > 0) {
                                            echo "<span class='fs-6' style='color: #0bd413;'>(+". $delta .")</span>";
                                        } else {
                                            echo "<span class='fs-6' style='color: #0070d1;'>(=)</span>";
                                        }
                                    }
                                    ?>
                                </h3>
                                <?php
                            } else {
                                ?>
                                <h3>N/A</h3>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
