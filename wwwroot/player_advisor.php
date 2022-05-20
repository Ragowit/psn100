<?php
if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

$query = $database->prepare("SELECT * FROM player WHERE account_id = :account_id");
$query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
$query->execute();
$player = $query->fetch();

$title = $player["online_id"] . "'s Trophy Advisor ~ PSN 100%";
require_once("player_header.php");

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
$trophyCount = $query->fetchColumn();

$page = max(isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1, 1);
$limit = 50;
$offset = ($page - 1) * $limit;
?>
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
            <div class="col-2 text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>/timeline">Timeline</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>/random">Random Games</a></h5>
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
                    if ($player["status"] == 3) {
                        ?>
                        <tr>
                            <td colspan="5" class="text-center"><h3>This player seems to have a <a href="https://www.playstation.com/en-us/support/account/privacy-settings-psn/">private</a> profile.</h3></td>
                        </tr>
                        <?php
                    } else {
                        $query = $database->prepare("SELECT
                                tt.id AS game_id,
                                tt.name AS game_name,
                                tt.platform,
                                tg.icon_url AS group_icon_url,
                                tg.name AS group_name,
                                t.id AS trophy_id,
                                t.icon_url AS trophy_icon_url,
                                t.name AS trophy_name,
                                t.detail AS trophy_detail,
                                t.rarity_percent,
                                t.type,
                                t.progress_target_value,
                                t.reward_name,
                                t.reward_image_url,
                                te.progress
                            FROM
                                trophy t
                            JOIN trophy_title tt USING(np_communication_id)
                            JOIN trophy_group tg USING(np_communication_id, group_id)
                            LEFT JOIN trophy_earned te ON
                                t.np_communication_id = te.np_communication_id AND t.group_id = te.group_id AND t.order_id = te.order_id AND te.account_id = :account_id
                            JOIN trophy_title_player ttp ON
                                t.np_communication_id = ttp.np_communication_id AND ttp.account_id = :account_id
                            WHERE
                                (te.earned IS NULL OR te.earned = 0) AND tt.status = 0 AND t.status = 0
                            ORDER BY
                                rarity_percent
                            DESC                    
                            LIMIT :offset, :limit");
                        $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                        $query->bindParam(":offset", $offset, PDO::PARAM_INT);
                        $query->bindParam(":limit", $limit, PDO::PARAM_INT);
                        $query->execute();
                        $trophies = $query->fetchAll();

                        foreach ($trophies as $trophy) {
                            $trophyIconHeight = 0;
                            if (str_contains($trophy["platform"], "PS5")) {
                                $trophyIconHeight = 64;
                            } else {
                                $trophyIconHeight = 60;
                            }
                            ?>
                            <tr>
                                <td>
                                    <a href="/game/<?= $trophy["game_id"] ."-". slugify($trophy["game_name"]); ?>/<?= $player["online_id"]; ?>">
                                        <img src="/img/group/<?= $trophy["group_icon_url"]; ?>" alt="<?= $trophy["group_name"]; ?>" title="<?= $trophy["group_name"]; ?>" style="background: linear-gradient(to bottom,#145EBB 0,#142788 100%);" width="100" />
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center" style="height: 64px; width: 64px;">
                                        <a href="/trophy/<?= $trophy["trophy_id"] ."-". slugify($trophy["trophy_name"]); ?>">
                                            <img src="/img/trophy/<?= $trophy["trophy_icon_url"]; ?>" alt="<?= $trophy["trophy_name"]; ?>" title="<?= $trophy["trophy_name"]; ?>" style="background: linear-gradient(to bottom,#145EBB 0,#142788 100%);" height="<?= $trophyIconHeight; ?>" />
                                        </a>
                                    </div>
                                </td>
                                <td style="width: 100%;">
                                    <a href="/trophy/<?= $trophy["trophy_id"] ."-". slugify($trophy["trophy_name"]); ?>/<?= $player["online_id"]; ?>">
                                        <b><?= htmlentities($trophy["trophy_name"]); ?></b>
                                    </a>
                                    <br>
                                    <?= nl2br(htmlentities($trophy["trophy_detail"], ENT_QUOTES, "UTF-8")); ?>
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
                                <td class="text-center">
                                    <?= $trophy["rarity_percent"]; ?>%<br>
                                    <?php
                                    if ($trophy["rarity_percent"] <= 0.02) {
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

        <div class="row">
            <div class="col-12">
                <nav aria-label="Page navigation">
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
                        if ($page+1 < ceil($trophyCount / $limit)+1) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page+1; ?>"><?= $page+1; ?></a></li>
                            <?php
                        }

                        if ($page+2 < ceil($trophyCount / $limit)+1) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page+2; ?>"><?= $page+2; ?></a></li>
                            <?php
                        }

                        if ($page < ceil($trophyCount / $limit)-2) {
                            ?>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                            <li class="page-item"><a class="page-link" href="?page=<?= ceil($trophyCount / $limit); ?>"><?= ceil($trophyCount / $limit); ?></a></li>
                            <?php
                        }

                        if ($page < ceil($trophyCount / $limit)) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page+1; ?>">Next</a></li>
                            <?php
                        }
                        ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</main>
<?php
require_once("footer.php");
?>
