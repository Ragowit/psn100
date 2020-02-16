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
require_once("player_header.php");
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
        </div>

        <div class="row">
            <div class="col-12">
                <nav aria-label="Page navigation">
                    <?php
                    $query = $database->prepare("SELECT SUM(tg.bronze-tgp.bronze + tg.silver-tgp.silver + tg.gold-tgp.gold + tg.platinum-tgp.platinum) FROM trophy_group_player tgp
                        JOIN trophy_group tg USING (np_communication_id, group_id)
                        JOIN trophy_title tt USING (np_communication_id)
                        WHERE tt.status = 0 AND tgp.account_id = :account_id");
                    $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                    $query->execute();
                    $result_count = $query->fetchColumn();

                    $page = max(isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1, 1);
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
                        if ($page+1 < ceil($result_count / $limit)+1) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page+1; ?>"><?= $page+1; ?></a></li>
                            <?php
                        }

                        if ($page+2 < ceil($result_count / $limit)+1) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page+2; ?>"><?= $page+2; ?></a></li>
                            <?php
                        }

                        if ($page < ceil($result_count / $limit)-2) {
                            ?>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                            <li class="page-item"><a class="page-link" href="?page=<?= ceil($result_count / $limit); ?>"><?= ceil($result_count / $limit); ?></a></li>
                            <?php
                        }

                        if ($page < ceil($result_count / $limit)) {
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
                    $query = $database->prepare("SELECT tt.id AS game_id, tt.name AS game_name, tg.icon_url AS group_icon_url, tg.name AS group_name, t.id AS trophy_id, t.icon_url AS trophy_icon_url, t.name AS trophy_name, t.detail AS trophy_detail, t.rarity_percent, t.type FROM trophy t
                        JOIN trophy_title tt USING (np_communication_id)
                        JOIN trophy_group tg USING (np_communication_id, group_id)
                        LEFT JOIN trophy_earned te ON t.np_communication_id = te.np_communication_id AND t.group_id = te.group_id AND t.order_id = te.order_id AND te.account_id = :account_id
                        JOIN trophy_title_player ttp ON t.np_communication_id = ttp.np_communication_id AND ttp.account_id = :account_id
                        WHERE te.id IS NULL AND tt.status = 0 AND t.status = 0
                        ORDER BY rarity_percent DESC
                        LIMIT :offset, :limit");
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
                                    <a href="/game/<?= $trophy["game_id"] ."-". slugify($trophy["game_name"]); ?>/<?= $player["online_id"]; ?>">
                                        <img src="/img/group/<?= $trophy["group_icon_url"]; ?>" alt="<?= $trophy["group_name"]; ?>" title="<?= $trophy["group_name"]; ?>" width="100" />
                                    </a>
                                </td>
                                <td>
                                    <img src="/img/trophy/<?= $trophy["trophy_icon_url"]; ?>" alt="<?= $trophy["trophy_name"]; ?>" title="<?= $trophy["trophy_name"]; ?>" width="44" />
                                </td>
                                <td style="width: 100%;">
                                    <a href="/trophy/<?= $trophy["trophy_id"] ."-". slugify($trophy["trophy_name"]); ?>/<?= $player["online_id"]; ?>">
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
