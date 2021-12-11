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
$query->bindParam(":id", $trophyId, PDO::PARAM_INT);
$query->execute();
$trophy = $query->fetch();

$trophyIconHeight = 0;
if (str_contains($trophy["platform"], "PS5")) {
    $trophyIconHeight = 64;
} else {
    $trophyIconHeight = 60;
}

if (isset($player)) {
    $query = $database->prepare("SELECT account_id FROM player WHERE online_id = :online_id");
    $query->bindParam(":online_id", $player, PDO::PARAM_STR);
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
            np_communication_id = :np_communication_id AND group_id = :group_id AND order_id = :order_id AND account_id = :account_id");
    $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
    $query->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
    $query->bindParam(":order_id", $trophy["order_id"], PDO::PARAM_INT);
    $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
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
<main role="main">
    <div class="container">
        <div class="row">
            <?php
            if ($trophy["status"] == 1) {
                ?>
                <div class="col-12">
                    <div class="alert alert-warning" role="alert">
                        This trophy is unobtainable and not accounted for on any leaderboard.
                    </div>
                </div>
                <?php
            }
            ?>

            <div class="col-2">
                <a href="/game/<?= $trophy["game_id"] ."-". slugify($trophy["game_name"]); ?>">
                    <img src="/img/title/<?= $trophy["game_icon"]; ?>" alt="<?= $trophy["game_name"]; ?>" title="<?= $trophy["game_name"]; ?>" style="background: linear-gradient(to bottom,#145EBB 0,#142788 100%);" height="60" />
                </a>
            </div>
            <div class="col-1 d-flex align-items-center justify-content-center" style="height: 64px; width: 64px;">
                <img src="/img/trophy/<?= $trophy["trophy_icon"]; ?>" alt="<?= $trophy["trophy_name"]; ?>" title="<?= $trophy["trophy_name"]; ?>" style="background: linear-gradient(to bottom,#145EBB 0,#142788 100%);" height="<?= $trophyIconHeight; ?>" />
            </div>
            <div class="col-7">
                <h5><?= htmlentities($trophy["trophy_name"]); ?></h5>
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
                <br>
                <?php
                if (isset($player)) {
                    ?>
                    <small style="font-style: italic;"><a href="/game/<?= $trophy["game_id"] ."-". slugify($trophy["game_name"]); ?>/<?= $player; ?>"><?= htmlentities($trophy["game_name"]); ?></a></small>
                    <?php
                } else {
                    ?>
                    <small style="font-style: italic;"><a href="/game/<?= $trophy["game_id"] ."-". slugify($trophy["game_name"]); ?>"><?= htmlentities($trophy["game_name"]); ?></a></small>
                    <?php
                }
                ?>
            </div>
            <div class="col-2">
                <div class="row">
                    <div class="col-6 text-center">
                        <?= $trophy["rarity_percent"]; ?>%<br>
                        <?php
                        if ($trophy["status"] == 1) {
                            echo "Unobtainable";
                        } elseif ($trophy["rarity_percent"] <= 0.50) {
                            echo "Legendary";
                        } elseif ($trophy["rarity_percent"] <= 2.50) {
                            echo "Epic";
                        } elseif ($trophy["rarity_percent"] <= 10.00) {
                            echo "Rare";
                        } elseif ($trophy["rarity_percent"] <= 25.00) {
                            echo "Uncommon";
                        } else {
                            echo "Common";
                        }
                        ?>
                    </div>
                    <div class="col-6 text-center">
                        <img src="/img/playstation/<?= $trophy["trophy_type"]; ?>.png" alt="<?= ucfirst($trophy["trophy_type"]); ?>" title="<?= ucfirst($trophy["trophy_type"]); ?>" />
                    </div>

                    <div class="col-12 text-center">
                        <?php
                        if (isset($playerTrophy) && $playerTrophy && $playerTrophy["earned"] == 1) {
                            echo "<span class=\"badge badge-pill badge-success\">Earned ". $playerTrophy["earned_date"] ."</span>";
                        }
                        ?>
                    </div>
                </div>
            </div>

        </div>

        <br>

        <div class="row">
            <div class="col-6">
                <div class="row">
                    <div class="col-12 text-center">
                        <h4>First Achievers</h4>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <table class="table table-responsive table-striped">
                            <?php
                            $query = $database->prepare("SELECT
                                    p.avatar_url,
                                    p.online_id,
                                    te.earned_date
                                FROM
                                    trophy_earned te
                                JOIN player p USING(account_id)
                                WHERE
                                    p.status = 0 AND p.rank <= 50000 AND te.np_communication_id = :np_communication_id AND te.group_id = :group_id AND te.order_id = :order_id AND te.earned = 1
                                ORDER BY
                                    - te.earned_date
                                DESC
                                LIMIT 50");
                            $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
                            $query->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
                            $query->bindParam(":order_id", $trophy["order_id"], PDO::PARAM_STR);
                            $query->execute();
                            $results = $query->fetchAll();

                            $count = 0;

                            foreach ($results as $result) {
                                ?>
                                <tr<?php if ($result["online_id"] == $player) {
                                    echo " class=\"table-success\"";
                                } ?>>
                                    <th class="align-middle" scope="row">
                                        <?= ++$count; ?>
                                    </th>
                                    <td>
                                        <img src="/img/avatar/<?= $result["avatar_url"]; ?>" alt="<?= $result["online_id"]; ?>" height="60" />
                                    </td>
                                    <td class="align-middle" width="100%">
                                        <a href="/game/<?= $trophy["game_id"] ."-". slugify($trophy["game_name"]); ?>/<?= $result["online_id"]; ?>"><?= $result["online_id"]; ?></a>
                                    </td>
                                    <td class="align-middle text-center" style="white-space: nowrap;">
                                        <?= str_replace(" ", "<br>", $result["earned_date"]); ?>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </table>
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
                        <table class="table table-responsive table-striped">
                            <?php
                            $query = $database->prepare("SELECT
                                    p.avatar_url,
                                    p.online_id,
                                    IFNULL(te.earned_date, 'No Timestamp') AS earned_date
                                FROM
                                    trophy_earned te
                                JOIN player p USING(account_id)
                                WHERE
                                    p.status = 0 AND p.rank <= 50000 AND te.np_communication_id = :np_communication_id AND te.group_id = :group_id AND te.order_id = :order_id AND te.earned = 1
                                ORDER BY
                                    te.earned_date
                                DESC
                                LIMIT 50");
                            $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
                            $query->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
                            $query->bindParam(":order_id", $trophy["order_id"], PDO::PARAM_STR);
                            $query->execute();
                            $results = $query->fetchAll();

                            $count = 0;

                            foreach ($results as $result) {
                                ?>
                                <tr<?php if ($result["online_id"] == $player) {
                                    echo " class=\"table-success\"";
                                } ?>>
                                    <th class="align-middle" scope="row">
                                        <?= ++$count; ?>
                                    </th>
                                    <td>
                                        <img src="/img/avatar/<?= $result["avatar_url"]; ?>" alt="<?= $result["online_id"]; ?>" height="60" />
                                    </td>
                                    <td class="align-middle" width="100%">
                                        <a href="/game/<?= $trophy["game_id"] ."-". slugify($trophy["game_name"]); ?>/<?= $result["online_id"]; ?>"><?= $result["online_id"]; ?></a>
                                    </td>
                                    <td class="align-middle text-center" style="white-space: nowrap;">
                                        <?= str_replace(" ", "<br>", $result["earned_date"]); ?>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
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
