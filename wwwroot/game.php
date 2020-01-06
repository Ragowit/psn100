<?php
if (!isset($gameId)) {
    header("Location: /game/", true, 303);
    die();
}

$query = $database->prepare("SELECT * FROM trophy_title WHERE id = :id");
$query->bindParam(":id", $gameId, PDO::PARAM_INT);
$query->execute();
$game = $query->fetch();

if (isset($player)) {
    $query = $database->prepare("SELECT account_id FROM player WHERE online_id = :online_id");
    $query->bindParam(":online_id", $player, PDO::PARAM_STR);
    $query->execute();
    $accountId = $query->fetchColumn();

    if ($accountId === false) {
        header("Location: /game/" . $game["id"] . "-" . str_replace(" ", "-", $game["name"]), true, 303);
        die();
    }
}

$title = $game["name"] . " Trophies ~ PSN100.net";
require_once("header.php");
?>
<main role="main">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1><?= $game["name"] ?></h1>
                <?php
                if (isset($player)) {
                    ?>
                    <small>Viewing as <a href="/player/<?= $player; ?>"><?= $player; ?></a></small>
                    <?php
                }
                ?>
            </div>

            <?php
            if (!empty($game["message"])) {
                ?>
                <div class="col-12">
                    <div class="alert alert-warning" role="alert">
                        <?= $game["message"]; ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>

        <div class="row">
            <div class="col-6 text-center">
                <h5>Trophies</h5>
            </div>
            <div class="col-6 text-center">
                <?php
                if (isset($player)) {
                    ?>
                    <h5><a href="/game-leaderboard/<?= $game["id"] . "-" . str_replace(" ", "-", $game["name"]); ?>/<?= $player; ?>">Leaderboard</a></h5>
                    <?php
                } else {
                    ?>
                    <h5><a href="/game-leaderboard/<?= $game["id"] . "-" . str_replace(" ", "-", $game["name"]); ?>">Leaderboard</a></h5>
                    <?php
                }
                ?>
            </div>
        </div>

        <div class="row">
            <div class="col-9">
                <div class="row" style="background: #b8daff;">
                    <?php
                    $query = $database->prepare("SELECT * FROM trophy_group WHERE np_communication_id = :np_communication_id AND group_id = 'default'");
                    $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                    $query->execute();
                    $group = $query->fetch();
                    ?>
                    <div class="col-auto">
                        <img src="/img/group/<?= $group["icon_url"]; ?>" alt="<?= $group["name"]; ?>" height="100" style="margin: 10px 0px;" />
                    </div>
                    <div class="col align-self-center">
                        <b><?= $group["name"]; ?></b><br>
                        <?= $group["detail"]; ?>
                    </div>
                    <div class="col-auto align-self-center">
                        <?= $group["bronze"]; ?> <img src="/img/playstation/bronze.png" alt="Bronze" width="24" />
                        <?= $group["silver"]; ?> <img src="/img/playstation/silver.png" alt="Silver" width="24" />
                        <?= $group["gold"]; ?> <img src="/img/playstation/gold.png" alt="Gold" width="24" />
                        <?= $group["platinum"]; ?> <img src="/img/playstation/platinum.png" alt="Platinum" width="24" />

                        <?php
                        if (isset($accountId)) {
                            $query = $database->prepare("SELECT progress FROM trophy_group_player WHERE np_communication_id = :np_communication_id AND group_id = 'default' AND account_id = :account_id");
                            $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                            $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                            $query->execute();
                            $progress = $query->fetchColumn(); ?>
                            <br>
                            <div class="progress">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $progress ?>%;" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"><?= $progress ?>%</div>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>

                <div class="row">
                    <table class="table table-responsive table-striped">
                        <?php
                        if (isset($accountId)) {
                            $query = $database->prepare("SELECT order_id, earned_date FROM trophy_earned WHERE np_communication_id = :np_communication_id AND group_id = 'default' AND account_id = :account_id");
                            $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                            $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                            $query->execute();
                            $earnedTrophies = $query->fetchAll(PDO::FETCH_KEY_PAIR);
                        }

                        $query = $database->prepare("SELECT * FROM trophy WHERE np_communication_id = :np_communication_id AND group_id = 'default' ORDER BY order_id");
                        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                        $query->execute();
                        while ($trophy = $query->fetch()) {
                            $trClass = "";
                            if ($trophy["status"] == 1) {
                                $trClass = " class=\"table-warning\" title=\"This trophy is unobtainable.\"";
                            } elseif (isset($earnedTrophies[$trophy["order_id"]])) {
                                $trClass = " class=\"table-success\"";
                            } ?>
                            <tr<?= $trClass; ?>>
                                <td><img src="/img/trophy/<?= $trophy["icon_url"]; ?>" alt="Trophy" height="60" /></td>
                                <td style="width: 100%;">
                                    <?php
                                    if (isset($player)) {
                                        ?>
                                        <a href="/trophy/<?= $trophy["id"] . "-" . str_replace(" ", "-", $trophy["name"]); ?>/<?= $player; ?>">
                                            <b><?= $trophy["name"]; ?></b>
                                        </a>
                                        <?php
                                    } else {
                                        ?>
                                        <a href="/trophy/<?= $trophy["id"] . "-" . str_replace(" ", "-", $trophy["name"]); ?>">
                                            <b><?= $trophy["name"]; ?></b>
                                        </a>
                                        <?php
                                    } ?>
                                    <br>
                                    <?= $trophy["detail"]; ?>
                                </td>
                                <td class="text-center" style="white-space: nowrap;">
                                    <?php
                                    if (isset($earnedTrophies[$trophy["order_id"]])) {
                                        echo str_replace(" ", "<br>", $earnedTrophies[$trophy["order_id"]]);
                                    } ?>
                                </td>
                                <td class="text-center">
                                    <h5><?= $trophy["rarity_percent"]; ?>%</h5>
                                    <?php
                                    if ($trophy["status"] == 1) {
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
                                <td><img src="/img/playstation/<?= $trophy["type"]; ?>.png" alt="<?= ucfirst($trophy["type"]); ?>" title="<?= ucfirst($trophy["type"]); ?>" /></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </table>
                </div>

                <?php
                $trophyGroups = $database->prepare("SELECT * FROM trophy_group WHERE np_communication_id = :np_communication_id AND group_id != 'default' ORDER BY group_id");
                $trophyGroups->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                $trophyGroups->execute();
                while ($trophyGroup = $trophyGroups->fetch()) {
                    ?>
                    <br>
                    <div class="row" style="background: #b8daff;">
                        <div class="col-auto">
                            <img src="/img/group/<?= $trophyGroup["icon_url"]; ?>" alt="<?= $trophyGroup["name"]; ?>" height="100" style="margin: 10px 0px;" />
                        </div>
                        <div class="col align-self-center">
                            <b><?= $trophyGroup["name"]; ?></b><br>
                            <?= $trophyGroup["detail"]; ?>
                        </div>
                        <div class="col-auto align-self-center">
                            <?= $trophyGroup["bronze"]; ?> <img src="/img/playstation/bronze.png" alt="Bronze" width="24" />
                            <?= $trophyGroup["silver"]; ?> <img src="/img/playstation/silver.png" alt="Silver" width="24" />
                            <?= $trophyGroup["gold"]; ?> <img src="/img/playstation/gold.png" alt="Gold" width="24" />

                            <?php
                            if (isset($accountId)) {
                                $query = $database->prepare("SELECT progress FROM trophy_group_player WHERE np_communication_id = :np_communication_id AND group_id = :group_id AND account_id = :account_id");
                                $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                                $query->bindParam(":group_id", $trophyGroup["group_id"], PDO::PARAM_STR);
                                $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                                $query->execute();
                                $progress = $query->fetchColumn(); ?>
                                <br>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $progress ?>%;" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"><?= $progress ?>%</div>
                                </div>
                                <?php
                            } ?>
                        </div>
                    </div>

                    <?php
                    if (isset($accountId)) {
                        $query = $database->prepare("SELECT order_id, earned_date FROM trophy_earned WHERE np_communication_id = :np_communication_id AND group_id = :group_id AND account_id = :account_id");
                        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                        $query->bindParam(":group_id", $trophyGroup["group_id"], PDO::PARAM_STR);
                        $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                        $query->execute();
                        $earnedTrophies = $query->fetchAll(PDO::FETCH_KEY_PAIR);
                    }

                    $trophies = $database->prepare("SELECT * FROM trophy WHERE np_communication_id = :np_communication_id AND group_id = :group_id ORDER BY order_id");
                    $trophies->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                    $trophies->bindParam(":group_id", $trophyGroup["group_id"], PDO::PARAM_STR);
                    $trophies->execute(); ?>
                    <div class="row">
                        <table class="table table-responsive table-striped">
                            <?php
                            while ($trophy = $trophies->fetch()) {
                                $trClass = "";
                                if ($trophy["status"] == 1) {
                                    $trClass = " class=\"table-warning\" title=\"This trophy is unobtainable.\"";
                                } elseif (isset($earnedTrophies[$trophy["order_id"]])) {
                                    $trClass = " class=\"table-success\"";
                                } ?>
                                <tr<?= $trClass; ?>>
                                    <td><img src="/img/trophy/<?= $trophy["icon_url"]; ?>" alt="Trophy" height="60" /></td>
                                    <td style="width: 100%;">
                                        <?php
                                        if (isset($player)) {
                                            ?>
                                            <a href="/trophy/<?= $trophy["id"] . "-" . str_replace(" ", "-", $trophy["name"]); ?>/<?= $player; ?>">
                                                <b><?= $trophy["name"]; ?></b>
                                            </a>
                                            <?php
                                        } else {
                                            ?>
                                            <a href="/trophy/<?= $trophy["id"] . "-" . str_replace(" ", "-", $trophy["name"]); ?>">
                                                <b><?= $trophy["name"]; ?></b>
                                            </a>
                                            <?php
                                        } ?>
                                        <br>
                                        <?= $trophy["detail"]; ?>
                                    </td>
                                    <td class="text-center" style="white-space: nowrap">
                                        <?php
                                        if (isset($earnedTrophies[$trophy["order_id"]])) {
                                            echo "<i>" . str_replace(" ", "<br>", $earnedTrophies[$trophy["order_id"]]) . "</i>";
                                        } ?>
                                    </td>
                                    <td class="text-center">
                                        <h5><?= $trophy["rarity_percent"]; ?>%</h5>
                                        <?php
                                        if ($trophy["status"] == 1) {
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
                                    <td><img src="/img/playstation/<?= $trophy["type"]; ?>.png" alt="<?= ucfirst($trophy["type"]); ?>" /></td>
                                </tr>
                                <?php
                            } ?>
                        </table>
                    </div>
                    <?php
                }
                ?>
            </div>
            <div class="col-3">
                <div class="row">
                    <div class="col-12 text-center">
                        <img src="/img/title/<?= $game["icon_url"]; ?>" alt="<?= $game["name"]; ?>" width="250" />
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 text-center">
                        <?php
                        foreach (explode(",", $game["platform"]) as $platform) {
                            echo "<span class=\"badge badge-pill badge-primary\">" . $platform . "</span> ";
                        }
                        ?>
                    </div>

                    <div class="col-12 text-center">
                        <?= $game["bronze"]; ?> <img src="/img/playstation/bronze.png" alt="Bronze" width="24" />
                        <?= $game["silver"]; ?> <img src="/img/playstation/silver.png" alt="Silver" width="24" />
                        <?= $game["gold"]; ?> <img src="/img/playstation/gold.png" alt="Gold" width="24" />
                        <?= $game["platinum"]; ?> <img src="/img/playstation/platinum.png" alt="Platinum" width="24" />
                    </div>

                    <?php
                    if (isset($accountId)) {
                        $query = $database->prepare("SELECT progress FROM trophy_title_player WHERE np_communication_id = :np_communication_id AND account_id = :account_id");
                        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                        $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                        $query->execute();
                        $progress = $query->fetchColumn(); ?>
                        <div class="col-12 text-center">
                            <div class="progress">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $progress ?>%;" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"><?= $progress ?>%</div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>

                    <div class="col-12 text-center">
                        <?php
                        $query = $database->prepare("SELECT SUM(rarity_point) FROM trophy WHERE np_communication_id = :np_communication_id AND status = 0");
                        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                        $query->execute();
                        $rarityPoints = $query->fetchColumn();

                        $query = $database->prepare("SELECT COUNT(*) FROM trophy_title_player ttp JOIN player p USING (account_id) WHERE p.status = 0 AND ttp.progress = 100 AND ttp.np_communication_id = :np_communication_id");
                        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                        $query->execute();
                        $ownersCompleted = $query->fetchColumn();
                        ?>
                        <span title="<?= $ownersCompleted; ?> of <?= $game["owners"]; ?> players have 100% this game."><?= $game["difficulty"]; ?>% Completion Rate</span><br>
                        <?= $rarityPoints; ?> Rarity Points
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <th class="table-primary" colspan="2">Recent Players</th>
                            </thead>
                            <tbody>
                                <?php
                                $query = $database->prepare("SELECT p.online_id, p.avatar_url, ttp.bronze, ttp.silver, ttp.gold, ttp.platinum, ttp.progress, ttp.last_updated_date FROM trophy_title_player ttp JOIN player p USING (account_id) WHERE p.status = 0 AND ttp.np_communication_id = :np_communication_id ORDER BY ttp.last_updated_date DESC LIMIT 10");
                                $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                                $query->execute();
                                $recentPlayers = $query->fetchAll();

                                foreach ($recentPlayers as $recentPlayer) {
                                    ?>
                                    <tr>
                                        <td>
                                            <img src="/img/avatar/<?= $recentPlayer["avatar_url"]; ?>" alt="" height="25" />
                                        </td>
                                        <td>
                                            <a href="/game/<?= $game["id"] . "-" . str_replace(" ", "-", $game["name"]); ?>/<?= $recentPlayer["online_id"]; ?>"><?= $recentPlayer["online_id"]; ?></a>
                                            <br>
                                            <?= $recentPlayer["last_updated_date"]; ?>
                                            <br>
                                            <?= $recentPlayer["bronze"]; ?> <img src="/img/playstation/bronze.png" alt="Bronze" width="24" />
                                            <?= $recentPlayer["silver"]; ?> <img src="/img/playstation/silver.png" alt="Silver" width="24" />
                                            <?= $recentPlayer["gold"]; ?> <img src="/img/playstation/gold.png" alt="Gold" width="24" />
                                            <?= $recentPlayer["platinum"]; ?> <img src="/img/playstation/platinum.png" alt="Platinum" width="24" />
                                            <br>
                                            <div class="progress">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $recentPlayer["progress"]; ?>%;" aria-valuenow="<?= $recentPlayer["progress"]; ?>" aria-valuemin="0" aria-valuemax="100"><?= $recentPlayer["progress"]; ?>%</div>
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
    </div>
</main>
<?php
require_once("footer.php");
?>
