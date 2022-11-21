<?php
if (!isset($gameId)) {
    header("Location: /game/", true, 303);
    die();
}

$query = $database->prepare("SELECT * 
    FROM   trophy_title 
    WHERE  id = :id ");
$query->bindParam(":id", $gameId, PDO::PARAM_INT);
$query->execute();
$game = $query->fetch();

if (isset($player)) {
    $query = $database->prepare("SELECT account_id 
        FROM   player 
        WHERE  online_id = :online_id ");
    $query->bindParam(":online_id", $player, PDO::PARAM_STR);
    $query->execute();
    $accountId = $query->fetchColumn();

    if ($accountId === false) {
        header("Location: /game/". $game["id"] ."-". slugify($game["name"]), true, 303);
        die();
    }
}

$metaData = new stdClass();
$metaData->title = $game["name"] ." Trophies";
$metaData->description = $game["bronze"] ." Bronze ~ ". $game["silver"] ." Silver ~ ". $game["gold"] ." Gold ~ ". $game["platinum"] ." Platinum";
$metaData->image = "https://psn100.net/img/title/". $game["icon_url"];
$metaData->url = "https://psn100.net/game/". $game["id"] ."-". slugify($game["name"]);

$title = $game["name"] ." Trophies ~ PSN 100%";
require_once("header.php");
?>
<main role="main">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1><?= htmlentities($game["name"]) ?></h1>
                <?php
                if (isset($player)) {
                    ?>
                    <small>Viewing as <a href="/player/<?= $player; ?>"><?= $player; ?></a></small>
                    <?php
                }
                ?>
            </div>

            <?php
            if ($game["status"] == 2) {
                ?>
                <div class="col-12">
                    <div class="alert alert-warning" role="alert">
                        This game have been merged, please search for the parent game. Earned trophies in this entry will not be accounted for on any leaderboard but have been transfered to the parent game.
                    </div>
                </div>
                <?php
            }
            ?>

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
                    <h5><a href="/game-leaderboard/<?= $game["id"] ."-". slugify($game["name"]); ?>/<?= $player; ?>">Leaderboard</a></h5>
                    <?php
                } else {
                    ?>
                    <h5><a href="/game-leaderboard/<?= $game["id"] ."-". slugify($game["name"]); ?>">Leaderboard</a></h5>
                    <?php
                }
                ?>
            </div>
        </div>

        <div class="row">
            <div class="col-9">
                <?php
                $trophyGroups = $database->prepare("SELECT * 
                    FROM   trophy_group 
                    WHERE  np_communication_id = :np_communication_id 
                        AND group_id = 'default' 
                    UNION 
                    (SELECT * 
                    FROM   trophy_group 
                    WHERE  np_communication_id = :np_communication_id 
                            AND group_id != 'default' 
                    ORDER  BY group_id) ");
                $trophyGroups->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                $trophyGroups->execute();
                while ($trophyGroup = $trophyGroups->fetch()) {
                    ?>
                    <div id="<?= $trophyGroup["group_id"]; ?>" class="row" style="background: #b8daff;">
                        <div class="col-auto">
                            <img src="/img/group/<?= ($trophyGroup["icon_url"] == ".png") ? ((str_contains($game["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $trophyGroup["icon_url"]; ?>" alt="<?= $trophyGroup["name"]; ?>" height="100" style="background: linear-gradient(to bottom,#145EBB 0,#142788 100%); margin: 10px 0px;" />
                        </div>
                        <div class="col align-self-center">
                            <b><?= htmlentities($trophyGroup["name"]); ?></b><br>
                            <?= nl2br(htmlentities($trophyGroup["detail"], ENT_QUOTES, "UTF-8")); ?>
                        </div>
                        <div class="col-auto align-self-center">
                            <?= $trophyGroup["bronze"]; ?> <img src="/img/playstation/bronze.png" alt="Bronze" width="24" />
                            <?= $trophyGroup["silver"]; ?> <img src="/img/playstation/silver.png" alt="Silver" width="24" />
                            <?= $trophyGroup["gold"]; ?> <img src="/img/playstation/gold.png" alt="Gold" width="24" />

                            <?php
                            if (isset($accountId)) {
                                $query = $database->prepare("SELECT progress 
                                    FROM   trophy_group_player 
                                    WHERE  np_communication_id = :np_communication_id 
                                        AND group_id = :group_id 
                                        AND account_id = :account_id ");
                                $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                                $query->bindParam(":group_id", $trophyGroup["group_id"], PDO::PARAM_STR);
                                $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                                $query->execute();
                                $progress = $query->fetchColumn();
                                if ($progress != false) {
                                    ?>
                                    <br>
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $progress ?>%;" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"><?= $progress ?>%</div>
                                    </div>
                                    <?php
                                }
                            } ?>
                        </div>
                    </div>

                    <?php
                    if (isset($accountId)) {
                        $queryText = "SELECT * 
                            FROM   (SELECT t.id, 
                                         t.order_id, 
                                         t.type, 
                                         t.name, 
                                         t.detail, 
                                         t.icon_url, 
                                         t.rarity_percent, 
                                         t.status,
                                         t.progress_target_value,
                                         t.reward_name,
                                         t.reward_image_url,
                                         te.earned_date,
                                         te.progress,
                                         te.earned
                                  FROM   trophy t 
                                         LEFT JOIN (SELECT np_communication_id, 
                                                           group_id, 
                                                           order_id, 
                                                           Ifnull(earned_date, 'No Timestamp') AS 
                                                           earned_date,
                                                           progress,
                                                           earned
                                                    FROM   trophy_earned 
                                                    WHERE  account_id = :account_id) AS te USING ( 
                                         np_communication_id, group_id, order_id) 
                                  WHERE  t.np_communication_id = :np_communication_id 
                                         AND t.group_id = :group_id) AS x ";

                        if (isset($_GET["order"]) && $_GET["order"] == "date") {
                            $queryText = $queryText ." ORDER  BY x.earned_date IS NULL, 
                                x.earned_date, 
                                Field(x.type, 'bronze', 'silver', 'gold', 'platinum'),
                                x.order_id ";
                        } elseif (isset($_GET["order"]) && $_GET["order"] == "rarity") {
                            $queryText = $queryText ." ORDER  BY x.rarity_percent DESC, 
                                Field(x.type, 'bronze', 'silver', 'gold', 'platinum'), 
                                x.order_id ";
                        } else {
                            $queryText = $queryText ." ORDER  BY x.order_id ";
                        }

                        $query = $database->prepare($queryText);
                        $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                    } else {
                        $queryText = "SELECT t.id, 
                                   t.order_id, 
                                   t.type, 
                                   t.name, 
                                   t.detail, 
                                   t.icon_url, 
                                   t.rarity_percent, 
                                   t.status,
                                   t.progress_target_value,
                                   t.reward_name,
                                   t.reward_image_url
                            FROM   trophy t 
                            WHERE  t.np_communication_id = :np_communication_id 
                                   AND t.group_id = :group_id ";

                        if (isset($_GET["order"]) && $_GET["order"] == "rarity") {
                            $queryText = $queryText ." ORDER BY  rarity_percent DESC,
                                Field(type, 'bronze', 'silver', 'gold', 'platinum'),
                                order_id ";
                        } else {
                            $queryText = $queryText ." ORDER BY order_id";
                        }

                        $query = $database->prepare($queryText);
                    }
                    $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                    $query->bindParam(":group_id", $trophyGroup["group_id"], PDO::PARAM_STR);
                    $query->execute();
                    $trophies = $query->fetchAll(); ?>
                    <div class="row">
                        <table class="table table-responsive table-striped">
                            <?php
                            foreach ($trophies as $trophy) {
                                // A game can have been updated with a progress_target_value, while the user earned the trophy while it hadn't one. This fixes this issue.
                                if (isset($accountId) && $trophy["earned"] == 1 && $trophy["progress_target_value"] != null) {
                                    $trophy["progress"] = $trophy["progress_target_value"];
                                }

                                $trClass = "";
                                if ($trophy["status"] == 1) {
                                    $trClass = " class=\"table-warning\" title=\"This trophy is unobtainable and not accounted for on any leaderboard.\"";
                                } elseif (isset($accountId) && $trophy["earned"] == 1) {
                                    $trClass = " class=\"table-success\"";
                                }

                                $trophyIconHeight = 0;
                                if (str_contains($game["platform"], "PS5")) {
                                    $trophyIconHeight = 64;
                                } else {
                                    $trophyIconHeight = 60;
                                }
                                ?>
                                <tr<?= $trClass; ?>>
                                    <td>
                                        <div style="height: 64px; width: 64px;" class="d-flex align-items-center justify-content-center">
                                            <img src="/img/trophy/<?= ($trophy["icon_url"] == ".png") ? ((str_contains($game["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-trophy.png") : $trophy["icon_url"]; ?>" alt="Trophy" style="background: linear-gradient(to bottom,#145EBB 0,#142788 100%);" height="<?= $trophyIconHeight; ?>" />
                                        </div>
                                    </td>
                                    <td style="width: 100%;">
                                        <?php
                                        if (isset($player)) {
                                            ?>
                                            <a href="/trophy/<?= $trophy["id"] ."-". slugify($trophy["name"]); ?>/<?= $player; ?>">
                                                <b><?= htmlentities($trophy["name"]); ?></b>
                                            </a>
                                            <?php
                                        } else {
                                            ?>
                                            <a href="/trophy/<?= $trophy["id"] ."-". slugify($trophy["name"]); ?>">
                                                <b><?= htmlentities($trophy["name"]); ?></b>
                                            </a>
                                            <?php
                                        } ?>
                                        <br>
                                        <?= nl2br(htmlentities($trophy["detail"], ENT_QUOTES, "UTF-8")); ?>
                                        <?php
                                        if ($trophy["progress_target_value"] != null) {
                                            echo "<br><b>";
                                            if (isset($trophy["progress"])) {
                                                echo $trophy["progress"];
                                            } else {
                                                echo "0";
                                            }
                                            echo "/". $trophy["progress_target_value"] ."</b>";
                                        }

                                        if ($trophy["reward_name"] != null && $trophy["reward_image_url"] != null) {
                                            echo "<br>Reward: <a href='/img/reward/". $trophy["reward_image_url"] ."'>". $trophy["reward_name"] ."</a>";
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center" style="white-space: nowrap">
                                        <?php
                                        if (isset($accountId) && $trophy["earned"] == 1) {
                                            echo str_replace(" ", "<br>", $trophy["earned_date"]);
                                            if (isset($_GET["order"]) && $_GET["order"] == "date" && isset($previousTimeStamp) && $previousTimeStamp != "No Timestamp" && $trophy["earned_date"] != "No Timestamp") {
                                                echo "<br>";
                                                $datetime1 = date_create($previousTimeStamp);
                                                $datetime2 = date_create($trophy["earned_date"]);
                                                $completionTimes = explode(", ", date_diff($datetime1, $datetime2)->format("%y years, %m months, %d days, %h hours, %i minutes, %s seconds"));
                                                $first = -1;
                                                $second = -1;
                                                for ($i = 0; $i < count($completionTimes); $i++) {
                                                    if ($completionTimes[$i][0] == "0") {
                                                        continue;
                                                    }

                                                    if ($first == -1) {
                                                        $first = $i;
                                                    } elseif ($second == -1) {
                                                        $second = $i;
                                                    }
                                                }

                                                if ($first >= 0 && $second >= 0) {
                                                    echo "(+". $completionTimes[$first] .", ". $completionTimes[$second] .")";
                                                } elseif ($first >= 0 && $second == -1) {
                                                    echo "(+". $completionTimes[$first] .")";
                                                }
                                            }
                                        } ?>
                                    </td>
                                    <td class="text-center">
                                        <h5><?= $trophy["rarity_percent"]; ?>%</h5>
                                        <?php
                                        if ($trophy["status"] == 1) {
                                            echo "Unobtainable";
                                        } elseif ($trophy["rarity_percent"] <= 0.02) {
                                            echo "Legendary";
                                        } elseif ($trophy["rarity_percent"] <= 0.2) {
                                            echo "Epic";
                                        } elseif ($trophy["rarity_percent"] <= 2) {
                                            echo "Rare";
                                        } elseif ($trophy["rarity_percent"] <= 20) {
                                            echo "Uncommon";
                                        } else {
                                            echo "Common";
                                        } ?>
                                    </td>
                                    <td><img src="/img/playstation/<?= $trophy["type"]; ?>.png" alt="<?= ucfirst($trophy["type"]); ?>" /></td>
                                </tr>
                                <?php
                                if (isset($_GET["order"]) && $_GET["order"] == "date") {
                                    $previousTimeStamp = $trophy["earned_date"];
                                }
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
                        <img src="/img/title/<?= ($game["icon_url"] == ".png") ? ((str_contains($game["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $game["icon_url"]; ?>" alt="<?= $game["name"]; ?>" style="background: linear-gradient(to bottom,#145EBB 0,#142788 100%);" width="250" />
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
                        $query = $database->prepare("SELECT progress 
                            FROM   trophy_title_player 
                            WHERE  np_communication_id = :np_communication_id 
                                AND account_id = :account_id ");
                        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                        $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                        $query->execute();
                        $progress = $query->fetchColumn();
                        if ($progress != false) {
                            ?>
                            <div class="col-12 text-center">
                                <div class="progress">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $progress ?>%;" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"><?= $progress ?>%</div>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    ?>

                    <div class="col-12 text-center">
                        <?php
                        $query = $database->prepare("SELECT Ifnull(Sum(rarity_point), 0) 
                            FROM   trophy 
                            WHERE  np_communication_id = :np_communication_id 
                                AND status = 0 ");
                        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                        $query->execute();
                        $rarityPoints = $query->fetchColumn();

                        $query = $database->prepare("SELECT Count(*) 
                            FROM   trophy_title_player ttp 
                                JOIN player p USING (account_id) 
                            WHERE  p.status = 0 
                                AND ttp.progress = 100 
                                AND ttp.np_communication_id = :np_communication_id ");
                        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                        $query->execute();
                        $ownersCompleted = $query->fetchColumn();
                        ?>
                        <span><?= number_format($ownersCompleted); ?> of <?= number_format($game["owners"]); ?> players (<?= $game["difficulty"]; ?>%)<br>have 100% this game.</span><br>
                        <?php
                        switch($game["status"]) {
                            case 1:
                                echo "<span class=\"badge badge-pill badge-warning\">Delisted</span>";
                                break;
                            case 2:
                                echo "<span class=\"badge badge-pill badge-warning\">Merged</span>";
                                break;
                            case 3:
                                echo "<span class=\"badge badge-pill badge-warning\">Obsolete</span>";
                                break;
                            case 4:
                                echo "<span class=\"badge badge-pill badge-warning\">Delisted &amp; Obsolete</span>";
                                break;
                            default:
                                echo number_format($rarityPoints) ." Rarity Points";
                        }
                        echo "<br>";
                        echo "Version: ". $game["set_version"];
                        ?>
                    </div>

                    <div class="col-12 text-center">
                        <b>Order By</b><br>
                        <a href="?">Default</a> ~ <a href="?order=rarity">Rarity</a>
                        <?php
                        if (isset($accountId)) {
                            echo " ~ <a href=\"?order=date\">Date</a>";
                        } ?>
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
                                $query = $database->prepare("SELECT
                                        p.online_id,
                                        p.avatar_url,
                                        ttp.bronze,
                                        ttp.silver,
                                        ttp.gold,
                                        ttp.platinum,
                                        ttp.progress,
                                        ttp.last_updated_date
                                    FROM
                                        trophy_title_player ttp
                                    JOIN player p USING(account_id)
                                    WHERE
                                        p.status = 0 AND p.rank <= 50000 AND ttp.np_communication_id = :np_communication_id
                                    ORDER BY
                                        last_updated_date
                                    DESC
                                    LIMIT 10");
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
                                            <a href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?>/<?= $recentPlayer["online_id"]; ?>"><?= $recentPlayer["online_id"]; ?></a>
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
