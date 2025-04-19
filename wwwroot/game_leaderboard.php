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

    $query = $database->prepare("SELECT *
        FROM trophy_title_player
        WHERE np_communication_id = :np_communication_id AND account_id = :account_id");
    $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
    $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
    $query->execute();
    $gamePlayer = $query->fetch();
}

$title = $game["name"] ." Leaderboard ~ PSN 100%";
require_once("header.php");

$url = $_SERVER["REQUEST_URI"];
$url_parts = parse_url($url);
// If URL doesn't have a query string.
if (isset($url_parts["query"])) { // Avoid 'Undefined index: query'
    parse_str($url_parts["query"], $params);
} else {
    $params = array();
}

$sql = "SELECT COUNT(*) FROM trophy_title_player ttp
    JOIN (SELECT account_id, avatar_url, RANK() OVER (ORDER BY `points` DESC, `platinum` DESC, `gold` DESC, `silver` DESC) `ranking` FROM player WHERE `status` = 0";
if (isset($_GET["country"])) {
    $sql .= " AND `country` = :country";
}
$sql .= ") p USING (account_id)
    WHERE ttp.np_communication_id = :np_communication_id AND p.ranking <= 10000";
if (isset($_GET["avatar"])) {
    $sql .= " AND p.avatar_url = :avatar";
}
$query = $database->prepare($sql);
$query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
if (isset($_GET["country"])) {
    $country = $_GET["country"];
    $query->bindParam(":country", $country, PDO::PARAM_STR);
}
if (isset($_GET["avatar"])) {
    $avatar = $_GET["avatar"];
    $query->bindParam(":avatar", $avatar, PDO::PARAM_STR);
}
$query->execute();
$total_pages = $query->fetchColumn();

$page = max(isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1, 1);
$limit = 50;
$offset = ($page - 1) * $limit;

$paramsWithoutPage = $params;
unset($paramsWithoutPage["page"]);
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
                    <a class="btn btn-outline-primary" href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Trophies</a>
                    <a class="btn btn-primary active" href="/game-leaderboard/<?= $game["id"] ."-". slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Leaderboard</a>
                    <a class="btn btn-outline-primary" href="/game-recent-players/<?= $game["id"] ."-". slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Recent Players</a>
                </div>
            </div>

            <div class="col-3">
            </div>
        </div>
    </div>

    <div class="bg-body-tertiary p-3 rounded">
        <div class="row">
            <div class="col-12">
                <div class="table-responsive-xxl">
                    <table class="table">
                        <thead>
                            <tr class="text-uppercase">
                                <th scope="col">Rank</th>
                                <th scope="col">User</th>
                                <th scope="col" class="text-center">Date</th>
                                <th scope="col" class="text-center">Progress</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $sql = "SELECT
                                    p.account_id,
                                    p.avatar_url,
                                    p.country,
                                    p.online_id AS name,
                                    ttp.bronze,
                                    ttp.silver,
                                    ttp.gold,
                                    ttp.platinum,
                                    ttp.progress,
                                    ttp.last_updated_date AS last_known_date
                                FROM
                                    trophy_title_player ttp
                                    JOIN (SELECT account_id, avatar_url, country, online_id, RANK() OVER (ORDER BY `points` DESC, `platinum` DESC, `gold` DESC, `silver` DESC) `ranking` FROM player WHERE `status` = 0";
                            $sql .= ") p USING (account_id)";
                            $sql .= " WHERE
                                    ttp.np_communication_id = :np_communication_id AND p.ranking <= 10000";
                            if (isset($_GET["country"])) {
                                $sql .= " AND p.country = :country";
                            }
                            if (isset($_GET["avatar"])) {
                                $sql .= " AND p.avatar_url = :avatar";
                            }
                            $sql .= " ORDER BY
                                    progress DESC,
                                    platinum DESC,
                                    gold DESC,
                                    silver DESC,
                                    bronze DESC,
                                    last_known_date
                                LIMIT
                                    :offset, :limit";
                            $query = $database->prepare($sql);
                            if (isset($_GET["country"])) {
                                $country = $_GET["country"];
                                $query->bindParam(":country", $country, PDO::PARAM_STR);
                            }
                            if (isset($_GET["avatar"])) {
                                $avatar = $_GET["avatar"];
                                $query->bindParam(":avatar", $avatar, PDO::PARAM_STR);
                            }
                            $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                            $query->bindParam(":offset", $offset, PDO::PARAM_INT);
                            $query->bindParam(":limit", $limit, PDO::PARAM_INT);
                            $query->execute();
                            $rows = $query->fetchAll();

                            $rank = $offset;
                            foreach ($rows as $row) {
                                $countryName = Locale::getDisplayRegion("-" . $row["country"], 'en');
                                $paramsAvatar = $paramsWithoutPage;
                                $paramsAvatar["avatar"] = $row["avatar_url"];
                                $paramsCountry = $paramsWithoutPage;
                                $paramsCountry["country"] = $row["country"];
                                ?>
                                <tr<?= ((isset($accountId) && $row["account_id"] === $accountId) ? " class='table-primary'" : ""); ?>>
                                    <th class="align-middle" style="width: 2rem;" scope="row"><?= ++$rank; ?></th>

                                    <td>
                                        <div class="hstack gap-3">
                                            <div>
                                                <a href="?<?= http_build_query($paramsAvatar); ?>">
                                                    <img src="/img/avatar/<?= $row["avatar_url"]; ?>" alt="" height="50" width="50" />
                                                </a>
                                            </div>

                                            <div>
                                                <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?>/<?= $row["name"]; ?>"><?= $row["name"]; ?></a>
                                            </div>

                                            <div class="ms-auto">
                                                <a href="?<?= http_build_query($paramsCountry); ?>">
                                                    <img src="/img/country/<?= $row["country"]; ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
                                                </a>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="align-middle text-center" style="white-space: nowrap; width: 5rem;">
                                        <span id="date<?= $rank; ?>"></span>
                                        <script>
                                            document.getElementById("date<?= $rank; ?>").innerHTML = new Date('<?= $row["last_known_date"]; ?> UTC').toLocaleString('sv-SE').replace(' ', '<br>');
                                        </script>
                                    </td>

                                    <td class="align-middle text-center" style="white-space: nowrap; width: 10rem;">
                                        <div class="vstack gap-1">
                                            <div>
                                                <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $row["platinum"]; ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $row["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $row["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $row["bronze"]; ?></span>
                                            </div>

                                            <div>
                                                <div class="progress" role="progressbar" aria-label="Player game progress" aria-valuenow="<?= $row["progress"]; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <div class="progress-bar" style="width: <?= $row["progress"]; ?>%"><?= $row["progress"]; ?>%</div>
                                                </div>
                                            </div>
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

    <div class="row">
        <div class="col-12">
            <p class="text-center">
                <?= ($total_pages == 0 ? "0" : $offset + 1); ?>-<?= min($offset + $limit, $total_pages); ?> of <?= number_format($total_pages) ?>
            </p>
        </div>
        <div class="col-12">
            <nav aria-label="Game Leaderboard page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    if ($page > 1) {
                        $params["page"] = $page - 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>" aria-label="Previous">&lt;</a></li>
                        <?php
                    }

                    if ($page > 3) {
                        $params["page"] = 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">1</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <?php
                    }

                    if ($page-2 > 0) {
                        $params["page"] = $page - 2;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page-2; ?></a></li>
                        <?php
                    }

                    if ($page-1 > 0) {
                        $params["page"] = $page - 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page-1; ?></a></li>
                        <?php
                    }
                    ?>

                    <?php
                    $params["page"] = $page;
                    ?>
                    <li class="page-item active" aria-current="page"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page; ?></a></li>

                    <?php
                    if ($page+1 < ceil($total_pages / $limit)+1) {
                        $params["page"] = $page + 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page+1; ?></a></li>
                        <?php
                    }

                    if ($page+2 < ceil($total_pages / $limit)+1) {
                        $params["page"] = $page + 2;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page+2; ?></a></li>
                        <?php
                    }

                    if ($page < ceil($total_pages / $limit)-2) {
                        $params["page"] = ceil($total_pages / $limit);
                        ?>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= ceil($total_pages / $limit); ?></a></li>
                        <?php
                    }

                    if ($page < ceil($total_pages / $limit)) {
                        $params["page"] = $page + 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>" aria-label="Next">&gt;</a></li>
                        <?php
                    }
                    ?>
                </ul>
            </nav>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
