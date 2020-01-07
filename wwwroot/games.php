<?php
$title = "Games ~ PSN100.net";
require_once("header.php");
?>
<main role="main">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1>Games</h1>
            </div>
        </div>

        <?php
        $url = $_SERVER["REQUEST_URI"];
        $url_parts = parse_url($url);
        // If URL doesn't have a query string.
        if (isset($url_parts["query"])) { // Avoid 'Undefined index: query'
            parse_str($url_parts["query"], $params);
        } else {
            $params = array();
        }
        ?>

        <div class="row">
            <div class="col-12">
                <nav aria-label="Page navigation">
                    <?php
                    if (isset($_GET["search"])) {
                        $search = "%". $_GET["search"] ."%";

                        // Tried with "SELECT * FROM trophy_title WHERE MATCH(name) AGAINST (:search WITH QUERY EXPANSION) UNION SELECT * FROM trophy_title WHERE MATCH(name) AGAINST (:search IN BOOLEAN MODE) GROUP BY np_communication_id"
                        // before, but it didn't turn up as good as expected. Tried to search for Final Exam which is an exact title in the database, and turned up with Final Fantasy XV. Final Exam was in place 30.
                        // Also, the webhost provider uses an old version of MySQL so it required MyISAM instead if InnoDB to even work. So, until a better search function is found, use the standard LIKE query and InnoDB database.
                        $query = $database->prepare("SELECT COUNT(*) FROM trophy_title WHERE name LIKE :search");
                        $query->bindParam(":search", $search, PDO::PARAM_STR);
                    } else {
                        $query = $database->prepare("SELECT COUNT(*) FROM trophy_title");
                    }
                    $query->execute();
                    $total_pages = $query->fetchColumn();

                    $page = isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1;
                    $limit = 50;

                    $offset = ($page - 1) * $limit;
                    ?>
                    <ul class="pagination justify-content-center">
                        <?php
                        if ($page > 1) {
                            $params["page"] = $page - 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">Prev</a></li>
                            <?php
                        }

                        if ($page > 3) {
                            $params["page"] = 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">1</a></li>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                            <?php
                        }

                        if ($page-2 > 0) {
                            $params["page"] = $page - 2; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page-2; ?></a></li>
                            <?php
                        }

                        if ($page-1 > 0) {
                            $params["page"] = $page - 1; ?>
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
                            $params["page"] = $page + 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page+1; ?></a></li>
                            <?php
                        }

                        if ($page+2 < ceil($total_pages / $limit)+1) {
                            $params["page"] = $page + 2; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page+2; ?></a></li>
                            <?php
                        }

                        if ($page < ceil($total_pages / $limit)-2) {
                            $params["page"] = ceil($total_pages / $limit); ?>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= ceil($total_pages / $limit); ?></a></li>
                            <?php
                        }

                        if ($page < ceil($total_pages / $limit)) {
                            $params["page"] = $page + 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">Next</a></li>
                            <?php
                        }
                        ?>
                    </ul>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <table class="table table-responsive table-striped">
                    <tr>
                        <th scope="col" class="text-center">Icon</th>
                        <th scope="col" width="100%">Game Title</th>
                        <th scope="col" class="text-center">Platform</th>
                        <th scope="col" class="text-center"><img src="/img/playstation/trophies.png" alt="Trophies" width="50" /></th>
                        <th scope="col" class="text-center">Completion Rate</th>
                    </tr>

                    <?php
                    if (isset($_GET["search"])) {
                        $search = "%". $_GET["search"] ."%";

                        // Tried with "SELECT * FROM trophy_title WHERE MATCH(name) AGAINST (:search WITH QUERY EXPANSION) UNION SELECT * FROM trophy_title WHERE MATCH(name) AGAINST (:search IN BOOLEAN MODE) GROUP BY np_communication_id"
                        // before, but it didn't turn up as good as expected. Tried to search for Final Exam which is an exact title in the database, and turned up with Final Fantasy XV. Final Exam was in place 30.
                        // Also, the webhost provider uses an old version of MySQL so it required MyISAM instead if InnoDB to even work. So, until a better search function is found, use the standard LIKE query and InnoDB database.
                        $games = $database->prepare("SELECT * FROM trophy_title WHERE name LIKE :search LIMIT :offset, :limit");
                        $games->bindParam(":search", $search, PDO::PARAM_STR);
                        $games->bindParam(":offset", $offset, PDO::PARAM_INT);
                        $games->bindParam(":limit", $limit, PDO::PARAM_INT);
                    } else {
                        if (isset($_GET["sort"])) {
                            $games = $database->prepare("SELECT * FROM trophy_title ORDER BY difficulty DESC, owners DESC LIMIT :offset, :limit");
                        } else {
                            $games = $database->prepare("SELECT * FROM trophy_title ORDER BY id DESC LIMIT :offset, :limit");
                        }
                        $games->bindParam(":offset", $offset, PDO::PARAM_INT);
                        $games->bindParam(":limit", $limit, PDO::PARAM_INT);
                    }
                    $games->execute();
                    $games = $games->fetchAll();

                    if (isset($_GET["player"])) {
                        $player = $_GET["player"];
                        $playerQuery = $database->prepare("SELECT account_id FROM player WHERE online_id = :online_id");
                        $playerQuery->bindParam(":online_id", $player, PDO::PARAM_STR);
                        $playerQuery->execute();
                        $accountId = $playerQuery->fetchColumn();

                        $playerGamesQuery = $database->prepare("SELECT np_communication_id, progress FROM trophy_title_player WHERE account_id = :account_id");
                        $playerGamesQuery->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                        $playerGamesQuery->execute();
                        $playerGames = $playerGamesQuery->fetchAll(PDO::FETCH_KEY_PAIR);
                    }

                    foreach ($games as $game) {
                        if ($game["status"] == 1) {
                            echo "<tr class=\"table-warning\" title=\"This game is delisted, no trophies will be accounted for on any leaderboard.\">";
                        } elseif ($playerGames[$game["np_communication_id"]] == 100) {
                            echo "<tr class=\"table-success\">";
                        } else {
                            echo "<tr>";
                        }

                        $gameLink = $game["id"] . "-" . str_replace(" ", "-", $game["name"]);
                        if (isset($player)) {
                            $gameLink .= "/". $player;
                        } ?>
                            <td scope="row">
                                <a href="/game/<?= $gameLink; ?>">
                                    <img src="/img/title/<?= $game["icon_url"]; ?>" alt="" width="100" />
                                </a>
                            </td>
                            <td>
                                <a href="/game/<?= $gameLink; ?>">
                                    <?= $game["name"]; ?>
                                </a>
                            </td>
                            <td class="text-center">
                                <?php
                                foreach (explode(",", $game["platform"]) as $platform) {
                                    echo "<span class=\"badge badge-pill badge-primary\">" . $platform . "</span> ";
                                } ?>
                            </td>
                            <td class="text-center" style="white-space: nowrap;">
                                <?= $game["bronze"]; ?> <img src="/img/playstation/bronze.png" alt="Bronze" width="24" />
                                <?= $game["silver"]; ?> <img src="/img/playstation/silver.png" alt="Silver" width="24" />
                                <?= $game["gold"]; ?> <img src="/img/playstation/gold.png" alt="Gold" width="24" />
                                <?= $game["platinum"]; ?> <img src="/img/playstation/platinum.png" alt="Platinum" width="24" />
                                <br>
                                <?php
                                $query = $database->prepare("SELECT SUM(rarity_point) FROM trophy WHERE np_communication_id = :np_communication_id");
                        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                        $query->execute();
                        $rarityPoints = $query->fetchColumn(); ?>
                                <?= $rarityPoints; ?> Rarity Points
                            </td>
                            <td class="text-center">
                                <?= $game["difficulty"]; ?>%
                                <br>
                                <?= $game["owners"]; ?> owners
                            </td>
                        </tr>
                        <?php
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
