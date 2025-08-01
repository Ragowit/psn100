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

$title = $game["name"] ." Recent Players ~ PSN 100%";
require_once("header.php");

$url = $_SERVER["REQUEST_URI"];
$url_parts = parse_url($url);
// If URL doesn't have a query string.
if (isset($url_parts["query"])) { // Avoid 'Undefined index: query'
    parse_str($url_parts["query"], $params);
} else {
    $params = array();
}
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
                    <a class="btn btn-outline-primary" href="/game-leaderboard/<?= $game["id"] ."-". slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Leaderboard</a>
                    <a class="btn btn-primary active" href="/game-recent-players/<?= $game["id"] ."-". slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Recent Players</a>
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
                                <th scope="col"></th>
                                <th scope="col">User</th>
                                <th scope="col" class="text-center">Date</th>
                                <th scope="col" class="text-center">Progress</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $sql = "
                                SELECT
                                    p.account_id,
                                    p.avatar_url,
                                    p.country,
                                    p.online_id AS name,
                                    p.trophy_count_npwr,
                                    p.trophy_count_sony,
                                    ttp.bronze,
                                    ttp.silver,
                                    ttp.gold,
                                    ttp.platinum,
                                    ttp.progress,
                                    ttp.last_updated_date AS last_known_date
                                FROM
                                    trophy_title_player ttp
                                JOIN player p ON ttp.account_id = p.account_id
                                JOIN player_ranking r ON p.account_id = r.account_id
                                WHERE
                                    p.status = 0
                                    AND r.ranking <= 10000
                                    AND ttp.np_communication_id = :np_communication_id
                            ";
                            if (isset($_GET["country"])) {
                                $sql .= " AND p.country = :country";
                            }
                            if (isset($_GET["avatar"])) {
                                $sql .= " AND p.avatar_url = :avatar";
                            }
                            $sql .= "
                                ORDER BY
                                    ttp.last_updated_date DESC
                                LIMIT 10
                            ";

                            $query = $database->prepare($sql);

                            $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                            if (isset($_GET["country"])) {
                                $query->bindParam(":country", $_GET["country"], PDO::PARAM_STR);
                            }
                            if (isset($_GET["avatar"])) {
                                $query->bindParam(":avatar", $_GET["avatar"], PDO::PARAM_STR);
                            }

                            $query->execute();
                            $rows = $query->fetchAll();

                            $rank = 0;
                            foreach ($rows as $row) {
                                $countryName = Locale::getDisplayRegion("-" . $row["country"], 'en');
                                $paramsAvatar = $params;
                                $paramsAvatar["avatar"] = $row["avatar_url"];
                                $paramsCountry = $params;
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
                                                <?php
                                                if ($row["trophy_count_npwr"] < $row["trophy_count_sony"]) {
                                                    echo " <span style='color: #9d9d9d; font-weight: bold;'>(H)</span>";
                                                }
                                                ?>
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
</main>

<?php
require_once("footer.php");
?>
