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
        header("Location: /game-leaderboard/". $game["id"] ."-". slugify($game["name"]), true, 303);
        die();
    }
}

$title = $game["name"] . " Leaderboard ~ PSN100.net";
require_once("header.php");
?>
<main role="main">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1><?= $game["name"] ?></h1>
            </div>
        </div>

        <div class="row">
            <div class="col-6 text-center">
                <?php
                if (isset($player)) {
                    ?>
                    <h5><a href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?>/<?= $player; ?>">Trophies</a></h5>
                    <?php
                } else {
                    ?>
                    <h5><a href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?>">Trophies</a></h5>
                    <?php
                }
                ?>
            </div>
            <div class="col-6 text-center">
                <h5>Leaderboard</h5>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <nav aria-label="Page navigation">
                    <?php
                    $query = $database->prepare("SELECT COUNT(*) FROM trophy_title_player ttp JOIN player p USING (account_id) WHERE ttp.np_communication_id = :np_communication_id AND p.status = 0");
                    $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                    $query->execute();
                    $total_pages = $query->fetchColumn();

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
                <div class="row">
                    <table class="table table-responsive table-striped">
                        <?php
                        // Get Original Max Score, regardless of unobtainable trophy status
                        $query = $database->prepare("SELECT SUM(type = 'bronze')*15+SUM(type = 'silver')*30+SUM(type = 'gold')*90 AS max_score FROM trophy WHERE np_communication_id = :np_communication_id");
                        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                        $query->execute();
                        $maxScore = $query->fetchColumn();

                        $query = $database->prepare("SELECT p.account_id, p.avatar_url, p.country, p.online_id AS name, SUM(t.type = 'bronze') AS bronze, SUM(t.type = 'silver') AS silver, SUM(t.type = 'gold') AS gold, SUM(t.type = 'platinum') AS platinum, GREATEST(FLOOR((SUM(t.type = 'bronze')*15+SUM(t.type = 'silver')*30+SUM(t.type = 'gold')*90)/:max_score*100), 1) progress, ttp.last_updated_date FROM trophy t JOIN trophy_earned te USING (np_communication_id, group_id, order_id) JOIN trophy_title_player ttp USING (account_id, np_communication_id) JOIN player p USING (account_id) WHERE np_communication_id = :np_communication_id AND ttp.progress > 0 AND p.status = 0 GROUP BY account_id UNION SELECT p.account_id, p.avatar_url, p.country, p.online_id AS name, ttp.bronze, ttp.silver, ttp.gold, ttp.platinum, ttp.progress, ttp.last_updated_date FROM player p JOIN trophy_title_player ttp USING (account_id) WHERE np_communication_id = :np_communication_id AND ttp.progress = 0 AND p.status = 0 ORDER BY progress DESC, last_updated_date LIMIT :offset, :limit");
                        $query->bindParam(":max_score", $maxScore, PDO::PARAM_INT);
                        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                        $query->bindParam(":offset", $offset, PDO::PARAM_INT);
                        $query->bindParam(":limit", $limit, PDO::PARAM_INT);
                        $query->execute();
                        $rows = $query->fetchAll();

                        $rank = $offset;
                        foreach ($rows as $row) {
                            $countryName = Locale::getDisplayRegion("-" . $row["country"], 'en'); ?>
                            <tr<?php if ($row["account_id"] === $accountId) {
                                echo " class=\"table-success\"";
                            } ?>>
                                <th class="align-middle"><?= ++$rank; ?></th>
                                <td><img src="/img/avatar/<?= $row["avatar_url"]; ?>" alt="" width="50" /></td>
                                <td>
                                    <img src="/img/country/<?= $row["country"]; ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
                                </td>
                                <td class="align-middle" style="width: 100%;">
                                    <a href="/player/<?= $row["name"]; ?>"><?= $row["name"]; ?></a>
                                </td>
                                <td class="align-middle text-center" style="white-space: nowrap;">
                                    <?= str_replace(" ", "<br>", $row["last_updated_date"]); ?>
                                </td>
                                <td class="align-middle text-center" style="white-space: nowrap;">
                                    <?= $row["bronze"]; ?> <img src="/img/playstation/bronze.png" alt="Bronze" width="24" />
                                    <?= $row["silver"]; ?> <img src="/img/playstation/silver.png" alt="Silver" width="24" />
                                    <?= $row["gold"]; ?> <img src="/img/playstation/gold.png" alt="Gold" width="24" />
                                    <?= $row["platinum"]; ?> <img src="/img/playstation/platinum.png" alt="Platinum" width="24" />
                                    <br>
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $row["progress"]; ?>%;" aria-valuenow="<?= $row["progress"]; ?>" aria-valuemin="0" aria-valuemax="100"><?= $row["progress"]; ?>%</div>
                                    </div>
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
</main>
<?php
require_once("footer.php");
?>
