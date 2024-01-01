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

$sort = $_GET["sort"] ?? "default";

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

    $query = $database->prepare("SELECT *
        FROM trophy_title_player
        WHERE np_communication_id = :np_communication_id AND account_id = :account_id");
    $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
    $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
    $query->execute();
    $gamePlayer = $query->fetch();
}

$metaData = new stdClass();
$metaData->title = $game["name"] ." Trophies";
$metaData->description = $game["bronze"] ." Bronze ~ ". $game["silver"] ." Silver ~ ". $game["gold"] ." Gold ~ ". $game["platinum"] ." Platinum";
$metaData->image = "https://psn100.net/img/title/". $game["icon_url"];
$metaData->url = "https://psn100.net/game/". $game["id"] ."-". slugify($game["name"]);

$title = $game["name"] ." Trophies ~ PSN 100%";
require_once("header.php");
?>

<main class="container">
    <?php
    require_once("game_header.php");
    ?>

    <div class="p-3 mb-3">
        <div class="row">
            <div class="col-3">
            </div>

            <div class="col-6 text-center">
                <div class="btn-group">
                    <a class="btn btn-primary active" href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Trophies</a>
                    <a class="btn btn-outline-primary" href="/game-leaderboard/<?= $game["id"] ."-". slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Leaderboard</a>
                    <a class="btn btn-outline-primary" href="/game-recent-players/<?= $game["id"] ."-". slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Recent Players</a>
                </div>
            </div>

            <div class="col-3">
                <form>
                    <div class="input-group d-flex justify-content-end">
                        <select class="form-select" name="sort" onChange="this.form.submit()">
                            <option disabled>Sort by...</option>
                            <option value="default"<?= ($sort == "default" ? " selected" : ""); ?>>Default</option>
                            <?php
                            if (isset($player)) {
                                ?>
                                <option value="date"<?= ($sort == "date" ? " selected" : ""); ?>>Date</option>
                                <?php
                            }
                            ?>
                            <option value="rarity"<?= ($sort == "rarity" ? " selected" : ""); ?>>Rarity</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="bg-body-tertiary p-3 rounded">
        <div class="row">
            <div class="col-12">
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
                    if (isset($player)) {
                        $query = $database->prepare("SELECT * 
                            FROM   trophy_group_player 
                            WHERE  np_communication_id = :np_communication_id 
                                AND group_id = :group_id 
                                AND account_id = :account_id ");
                        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                        $query->bindParam(":group_id", $trophyGroup["group_id"], PDO::PARAM_STR);
                        $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                        $query->execute();
                        $trophyGroupPlayer = $query->fetch();
                    }

                    unset($previousTimeStamp);
                    ?>
                    <div class="table-responsive-xxl">
                        <table class="table" id="<?= $trophyGroup["group_id"]; ?>">
                            <thead>
                                <tr>
                                    <th scope="col" colspan="5" class="bg-dark-subtle">
                                        <div class="hstack gap-3">
                                            <div>
                                                <img class="card-img object-fit-cover" style="height: 7rem;" src="/img/group/<?= ($trophyGroup["icon_url"] == ".png") ? ((str_contains($game["platform"], "PS5") || str_contains($game["platform"], "PSVR2")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $trophyGroup["icon_url"]; ?>" alt="<?= htmlentities($trophyGroup["name"]); ?>">
                                            </div>
                                            
                                            <div>
                                                <b><?= htmlentities($trophyGroup["name"]); ?></b><br>
                                                <?= nl2br(htmlentities($trophyGroup["detail"], ENT_QUOTES, "UTF-8")); ?>
                                            </div>

                                            <div class="ms-auto">
                                                <?php
                                                if (isset($trophyGroupPlayer)) {
                                                    if ($trophyGroup["group_id"] == "default") {
                                                        ?>
                                                        <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $trophyGroupPlayer["platinum"]; ?>/<?= $trophyGroup["platinum"]; ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $trophyGroupPlayer["gold"]; ?>/<?= $trophyGroup["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $trophyGroupPlayer["silver"]; ?>/<?= $trophyGroup["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $trophyGroupPlayer["bronze"]; ?>/<?= $trophyGroup["bronze"]; ?></span>
                                                        <?php
                                                    } else {
                                                        ?>
                                                        <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $trophyGroupPlayer["gold"]; ?>/<?= $trophyGroup["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $trophyGroupPlayer["silver"]; ?>/<?= $trophyGroup["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $trophyGroupPlayer["bronze"]; ?>/<?= $trophyGroup["bronze"]; ?></span>
                                                        <?php
                                                    }
                                                    ?>
                                                    <div>
                                                        <div class="progress mt-1" role="progressbar" aria-label="Player trophy progress" aria-valuenow="<?= $trophyGroupPlayer["progress"]; ?>" aria-valuemin="0" aria-valuemax="100">
                                                            <div class="progress-bar" style="width: <?= $trophyGroupPlayer["progress"]; ?>%"><?= $trophyGroupPlayer["progress"]; ?>%</div>
                                                        </div>
                                                    </div>
                                                    <?php
                                                } else {
                                                    if ($trophyGroup["group_id"] == "default") {
                                                        ?>
                                                        <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $trophyGroup["platinum"]; ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $trophyGroup["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $trophyGroup["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $trophyGroup["bronze"]; ?></span>
                                                        <?php
                                                    } else {
                                                        ?>
                                                        <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $trophyGroup["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $trophyGroup["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $trophyGroup["bronze"]; ?></span>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
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

                                    if ($sort == "date") {
                                        $queryText = $queryText ." ORDER  BY x.earned_date IS NULL, 
                                            x.earned_date, 
                                            Field(x.type, 'bronze', 'silver', 'gold', 'platinum'),
                                            x.order_id ";
                                    } elseif ($sort == "rarity") {
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

                                    if ($sort == "rarity") {
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
                                $trophies = $query->fetchAll();

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
                                    ?>
                                    <tr scope="row"<?= $trClass; ?>>
                                        <td style="width: 5rem;">
                                            <div>
                                                <img class="card-img object-fit-scale" style="height: 5rem;" src="/img/trophy/<?= ($trophy["icon_url"] == ".png") ? ((str_contains($game["platform"], "PS5") || str_contains($game["platform"], "PSVR2")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-trophy.png") : $trophy["icon_url"]; ?>" alt="<?= htmlentities($trophy["name"]); ?>">
                                            </div>
                                        </td>

                                        <td class="w-auto">
                                            <div class="vstack">
                                                <span>
                                                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/trophy/<?= $trophy["id"] ."-". slugify($trophy["name"]); ?><?= (isset($player) ? "/".$player : ""); ?>">
                                                        <b><?= htmlentities($trophy["name"]); ?></b>
                                                    </a>
                                                </span>
                                                <?= nl2br(htmlentities($trophy["detail"], ENT_QUOTES, "UTF-8")); ?>
                                            </div>
                                        </td>

                                        <td style="width: 13rem;" class="text-end align-middle">
                                            <?php
                                            if (isset($accountId) && $trophy["earned"] == 1) {
                                                ?>
                                                <span id="earned<?= $trophy["order_id"]; ?>"></span>
                                                <script>
                                                    document.getElementById("earned<?= $trophy["order_id"]; ?>").innerHTML = new Date('<?= $trophy["earned_date"]; ?> UTC').toLocaleString('sv-SE').replace(' ', '<br>');
                                                </script>
                                                <?php
                                                if ($sort == "date" && isset($previousTimeStamp) && $previousTimeStamp != "No Timestamp" && $trophy["earned_date"] != "No Timestamp") {
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
                                                if ($sort == "date") {
                                                    $previousTimeStamp = $trophy["earned_date"];
                                                }
                                            }
                                            ?>
                                        </td>

                                        <td style="width: 5rem;" class="text-center align-middle">
                                            <?php
                                            if ($trophy["status"] == 1) {
                                                echo "<span>". $trophy["rarity_percent"] ."%<br>Unobtainable</span>";
                                            } elseif ($trophy["rarity_percent"] <= 0.02) {
                                                echo "<span class='trophy-legendary'>". $trophy["rarity_percent"] ."%<br>Legendary</span>";
                                            } elseif ($trophy["rarity_percent"] <= 0.2) {
                                                echo "<span class='trophy-epic'>". $trophy["rarity_percent"] ."%<br>Epic</span>";
                                            } elseif ($trophy["rarity_percent"] <= 2) {
                                                echo "<span class='trophy-rare'>". $trophy["rarity_percent"] ."%<br>Rare</span>";
                                            } elseif ($trophy["rarity_percent"] <= 20) {
                                                echo "<span class='trophy-uncommon'>". $trophy["rarity_percent"] ."%<br>Uncommon</span>";
                                            } else {
                                                echo "<span class='trophy-common'>". $trophy["rarity_percent"] ."%<br>Common</span>";
                                            }
                                            ?>
                                        </td>

                                        <td style="width: 5rem;" class="text-center align-middle">
                                            <img src="/img/trophy-<?= $trophy["type"]; ?>.svg" alt="<?= ucfirst($trophy["type"]); ?>" title="<?= ucfirst($trophy["type"]); ?>" height="50" />
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php
                }
                ?>
            </div>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
