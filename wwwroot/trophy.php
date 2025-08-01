<?php
if (!isset($trophyId)) {
    header("Location: /trophy/", true, 303);
    die();
}

$query = $database->prepare("SELECT
        t.id AS trophy_id,
        t.np_communication_id,
        t.group_id,
        t.order_id,
        t.type AS trophy_type,
        t.name AS trophy_name,
        t.detail AS trophy_detail,
        t.icon_url AS trophy_icon,
        t.rarity_percent,
        t.status,
        t.progress_target_value,
        t.reward_name,
        t.reward_image_url,
        tt.id AS game_id,
        tt.name AS game_name,
        tt.icon_url AS game_icon,
        tt.platform
    FROM
        trophy t
    JOIN trophy_title tt USING(np_communication_id)
    WHERE
        t.id = :id");
$query->bindValue(":id", $trophyId, PDO::PARAM_INT);
$query->execute();
$trophy = $query->fetch();

if (isset($player)) {
    $query = $database->prepare("SELECT account_id FROM player WHERE online_id = :online_id");
    $query->bindValue(":online_id", $player, PDO::PARAM_STR);
    $query->execute();
    $accountId = $query->fetchColumn();

    if ($accountId === false) {
        header("Location: /trophy/". $trophy["trophy_id"] ."-". slugify($trophy["trophy_name"]), true, 303);
        die();
    }

    $query = $database->prepare("SELECT
            earned_date,
            progress,
            earned
        FROM
            trophy_earned
        WHERE
            np_communication_id = :np_communication_id AND order_id = :order_id AND account_id = :account_id");
    $query->bindValue(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
    $query->bindValue(":order_id", $trophy["order_id"], PDO::PARAM_INT);
    $query->bindValue(":account_id", $accountId, PDO::PARAM_INT);
    $query->execute();
    $playerTrophy = $query->fetch();

    // A game can have been updated with a progress_target_value, while the user earned the trophy while it hadn't one. This fixes this issue.
    if ($playerTrophy && $playerTrophy["earned"] == 1 && !is_null($trophy["progress_target_value"])) {
        $playerTrophy["progress"] = $trophy["progress_target_value"];
    }
}

$metaData = new stdClass();
$metaData->title = $trophy["trophy_name"] ." Trophy";
$metaData->description = htmlentities($trophy["trophy_detail"], ENT_QUOTES, "UTF-8");
$metaData->image = "https://psn100.net/img/trophy/". $trophy["trophy_icon"];
$metaData->url = "https://psn100.net/trophy/". $trophy["trophy_id"] ."-". slugify($trophy["trophy_name"]);

$title = $trophy["trophy_name"] . " Trophy ~ PSN 100%";
require_once("header.php");
?>

<main class="container">
    <?php
    if ($trophy["status"] == 1) {
        ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning" role="alert">
                    This trophy is unobtainable and not accounted for on any leaderboard.
                </div>
            </div>
        </div>
        <?php
    }
    ?>

    <div class="row">
        <div class="col-12">
            <div class="card rounded-4">
                <div class="d-flex justify-content-center align-items-center">
                    <img class="card-img object-fit-cover rounded-4" style="height: 25rem;" src="/img/title/<?= ($trophy["game_icon"] == ".png") ? ((str_contains($trophy["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $trophy["game_icon"]; ?>" alt="<?= $trophy["game_name"]; ?>" title="<?= $trophy["game_name"]; ?>" />
                    <div class="card-img-overlay d-flex align-items-end">
                        <div class="bg-body-tertiary p-3 rounded w-100">
                            <div class="row">
                                <div class="col-8">
                                    <div class="hstack gap-3">
                                        <div>
                                            <img src="/img/trophy/<?= ($trophy["trophy_icon"] == ".png") ? ((str_contains($trophy["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-trophy.png") : $trophy["trophy_icon"]; ?>" alt="<?= $trophy["trophy_name"]; ?>" title="<?= $trophy["trophy_name"]; ?>" style="width: 5rem;" />
                                        </div>

                                        <div>
                                            <div class="vstack gap-1">
                                                <div class="hstack gap-3">
                                                    <div>
                                                        <b><?= htmlentities($trophy["trophy_name"]); ?></b>
                                                    </div>

                                                    <?php
                                                    if (isset($playerTrophy) && $playerTrophy && $playerTrophy["earned"] == 1) {
                                                        ?>
                                                        <div>
                                                            <span class="badge rounded-pill text-bg-success" id="earnedTrophy"></span>
                                                            <script>
                                                                document.getElementById("earnedTrophy").innerHTML = 'Earned ' + new Date('<?= $playerTrophy["earned_date"]; ?> UTC').toLocaleString('sv-SE');
                                                            </script>
                                                        </div>
                                                        <?php
                                                    }
                                                    ?>
                                                </div>

                                                <div>
                                                    <?= nl2br(htmlentities($trophy["trophy_detail"], ENT_QUOTES, "UTF-8")); ?>
                                                    <?php
                                                    if ($trophy["progress_target_value"] != null) {
                                                        echo "<br><b>";
                                                        if (isset($playerTrophy["progress"])) {
                                                            echo $playerTrophy["progress"];
                                                        } else {
                                                            echo "0";
                                                        }
                                                        echo "/". $trophy["progress_target_value"] ."</b>";
                                                    }

                                                    if ($trophy["reward_name"] != null && $trophy["reward_image_url"] != null) {
                                                        echo "<br>Reward: <a href='/img/reward/". $trophy["reward_image_url"] ."'>". $trophy["reward_name"] ."</a>";
                                                    }
                                                    ?>
                                                </div>
                                                
                                                <div>
                                                    <div class="hstack gap-1">
                                                        <?php
                                                        foreach (explode(",", $trophy["platform"]) as $platform) {
                                                            echo "<span class=\"badge rounded-pill text-bg-primary p-2\">". $platform ."</span> ";
                                                        }
                                                        ?>

                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $trophy["game_id"] ."-". slugify($trophy["game_name"]); ?><?= (isset($player) ? "/".$player : ""); ?>"><?= htmlentities($trophy["game_name"]); ?></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-2 text-center align-self-center">
                                    <?php
                                    if ($trophy["status"] == 1) {
                                        echo "Unobtainable";
                                    } elseif ($trophy["rarity_percent"] <= 0.02) {
                                        echo "<span class='trophy-legendary'>". $trophy["rarity_percent"] ."%<br>Legendary</span>";
                                    } elseif ($trophy["rarity_percent"] <= 0.2) {
                                        echo "<span class='trophy-epic'>". $trophy["rarity_percent"] ."%<br>Epic</span>";
                                    } elseif ($trophy["rarity_percent"] <= 2) {
                                        echo "<span class='trophy-rare'>". $trophy["rarity_percent"] ."%<br>Rare</span>";
                                    } elseif ($trophy["rarity_percent"] <= 10) {
                                        echo "<span class='trophy-uncommon'>". $trophy["rarity_percent"] ."%<br>Uncommon</span>";
                                    } else {
                                        echo "<span class='trophy-common'>". $trophy["rarity_percent"] ."%<br>Common</span>";
                                    }
                                    ?>
                                </div>
                                
                                <div class="col-2 text-center align-self-center">
                                    <img src="/img/trophy-<?= $trophy["trophy_type"]; ?>.svg" alt="<?= ucfirst($trophy["trophy_type"]); ?>" title="<?= ucfirst($trophy["trophy_type"]); ?>" height="50" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-body-tertiary p-3 rounded mt-3">
        <div class="row">
            <div class="col-6">
                <div class="row">
                    <div class="col-12 text-center">
                        <h4>First Achievers</h4>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="table-responsive-xxl">
                            <table class="table">
                                <thead>
                                    <tr class="text-uppercase">
                                        <th scope="col"></th>
                                        <th scope="col">User</th>
                                        <th scope="col" class="text-center">Date</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php
                                    $query = $database->prepare("
                                        WITH filtered_trophy_earned AS (
                                            SELECT
                                                account_id,
                                                earned_date
                                            FROM
                                                trophy_earned
                                            WHERE
                                                np_communication_id = :np_communication_id
                                                AND order_id = :order_id
                                                AND earned = 1
                                        )
                                        SELECT
                                            p.avatar_url,
                                            p.online_id,
                                            p.trophy_count_npwr,
                                            p.trophy_count_sony,
                                            IFNULL(te.earned_date, 'No Timestamp') AS earned_date
                                        FROM
                                            filtered_trophy_earned te
                                            JOIN player_ranking r ON te.account_id = r.account_id
                                            JOIN player p ON r.account_id = p.account_id
                                        WHERE
                                            r.ranking <= 10000
                                        ORDER BY
                                            te.earned_date IS NULL, te.earned_date
                                        LIMIT 50");
                                    $query->bindValue(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
                                    $query->bindValue(":order_id", $trophy["order_id"], PDO::PARAM_STR);
                                    $query->execute();
                                    $results = $query->fetchAll();

                                    $count = 0;

                                    foreach ($results as $result) {
                                        ?>
                                        <tr<?= ($result["online_id"] == $player) ? " class='table-primary'" : ""; ?>>
                                            <th class="align-middle" scope="row">
                                                <?= ++$count; ?>
                                            </th>
                                            <td class="w-100">
                                                <div class="hstack gap-3">
                                                    <img src="/img/avatar/<?= $result["avatar_url"]; ?>" alt="<?= $result["online_id"]; ?>" height="60" />
                                                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $trophy["game_id"] ."-". slugify($trophy["game_name"]); ?>/<?= $result["online_id"]; ?>"><?= $result["online_id"]; ?></a>
                                                    <?php
                                                    if ($result["trophy_count_npwr"] < $result["trophy_count_sony"]) {
                                                        echo " <span style='color: #9d9d9d; font-weight: bold;'>(H)</span>";
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td id="faDate<?= $count; ?>" class="align-middle text-center" style="white-space: nowrap;">
                                            </td>

                                            <script>
                                                document.getElementById("faDate<?= $count; ?>").innerHTML = new Date('<?= $result["earned_date"]; ?> UTC').toLocaleString('sv-SE').replace(' ', '<br>');
                                            </script>
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

            <div class="col-6">
                <div class="row">
                    <div class="col-12 text-center">
                        <h4>Latest Achievers</h4>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="table-responsive-xxl">
                            <table class="table">
                                <thead>
                                    <tr class="text-uppercase">
                                        <th scope="col"></th>
                                        <th scope="col">User</th>
                                        <th scope="col" class="text-center">Date</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php
                                    $query = $database->prepare("
                                        WITH filtered_trophy_earned AS (
                                            SELECT
                                                account_id,
                                                earned_date
                                            FROM
                                                trophy_earned
                                            WHERE
                                                np_communication_id = :np_communication_id
                                                AND order_id = :order_id
                                                AND earned = 1
                                        )
                                        SELECT
                                            p.avatar_url,
                                            p.online_id,
                                            p.trophy_count_npwr,
                                            p.trophy_count_sony,
                                            IFNULL(te.earned_date, 'No Timestamp') AS earned_date
                                        FROM
                                            filtered_trophy_earned te
                                            JOIN player_ranking r ON te.account_id = r.account_id
                                            JOIN player p ON r.account_id = p.account_id
                                        WHERE
                                            r.ranking <= 10000
                                        ORDER BY
                                            te.earned_date DESC
                                        LIMIT 50");
                                    $query->bindValue(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
                                    $query->bindValue(":order_id", $trophy["order_id"], PDO::PARAM_STR);
                                    $query->execute();
                                    $results = $query->fetchAll();

                                    $count = 0;

                                    foreach ($results as $result) {
                                        ?>
                                        <tr<?= ($result["online_id"] == $player) ? " class='table-primary'" : ""; ?>>
                                            <th class="align-middle" scope="row">
                                                <?= ++$count; ?>
                                            </th>
                                            <td class="w-100">
                                                <div class="hstack gap-3">
                                                    <img src="/img/avatar/<?= $result["avatar_url"]; ?>" alt="<?= $result["online_id"]; ?>" height="60" />
                                                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $trophy["game_id"] ."-". slugify($trophy["game_name"]); ?>/<?= $result["online_id"]; ?>"><?= $result["online_id"]; ?></a>
                                                    <?php
                                                    if ($result["trophy_count_npwr"] < $result["trophy_count_sony"]) {
                                                        echo " <span style='color: #9d9d9d; font-weight: bold;'>(H)</span>";
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td id="laDate<?= $count; ?>" class="align-middle text-center" style="white-space: nowrap;">
                                            </td>

                                            <script>
                                                document.getElementById("laDate<?= $count; ?>").innerHTML = new Date('<?= $result["earned_date"]; ?> UTC').toLocaleString('sv-SE').replace(' ', '<br>');
                                            </script>
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
    </div>
</main>

<?php
require_once("footer.php");
?>
