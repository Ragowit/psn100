<?php
if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

$query = $database->prepare("SELECT * FROM player WHERE account_id = :account_id");
$query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
$query->execute();
$player = $query->fetch();

$query = $database->prepare("SELECT COUNT(*) FROM trophy_title_player ttp JOIN trophy_title tt USING (np_communication_id) WHERE tt.status = 0 AND ttp.account_id = :account_id");
$query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
$query->execute();
$numberOfGames = $query->fetchColumn();

$metaData = new stdClass();
$metaData->title = $player["online_id"] ."'s Trophy Progress";
if ($player["status"] == 3) {
    $metaData->description = "The player is private.";
} else {
    $metaData->description = "Level ". $player["level"] .".". $player["progress"] ." ~ ". $numberOfGames ." Unique Games ~ ". $player["platinum"] ." Unique Platinums";
}
$metaData->image = "https://psn100.net/img/avatar/". $player["avatar_url"];
$metaData->url = "https://psn100.net/player/". $player["online_id"];

$url = $_SERVER["REQUEST_URI"];
$url_parts = parse_url($url);
// If URL doesn't have a query string.
if (isset($url_parts["query"])) { // Avoid 'Undefined index: query'
    parse_str($url_parts["query"], $params);
} else {
    $params = array();
}

$sort = (!empty($_GET["sort"]) ? $_GET["sort"] : (!empty($_GET["search"]) ? "search" : "date"));

$sql = "SELECT Count(*)
    FROM   trophy_title_player ttp
           JOIN trophy_title tt USING (np_communication_id)
    WHERE  tt.status != 2
           AND ttp.account_id = :account_id";
if (!empty($_GET["search"]) || $sort == "search") {
    $sql .= " AND (MATCH(tt.name) AGAINST (:search)) > 0";
}
if (!empty($_GET["uncompleted"])) {
    $sql .= " AND ttp.progress != 100 ";
}
if (!empty($_GET["ps3"]) || !empty($_GET["ps4"]) || !empty($_GET["ps5"]) || !empty($_GET["psvita"]) || !empty($_GET["psvr"]) || !empty($_GET["psvr2"])) {
    $sql .= " AND (";
    if (!empty($_GET["ps3"])) {
        $sql .= " tt.platform LIKE '%PS3%' OR";
    }
    if (!empty($_GET["ps4"])) {
        $sql .= " tt.platform LIKE '%PS4%' OR";
    }
    if (!empty($_GET["ps5"])) {
        $sql .= " tt.platform LIKE '%PS5%' OR";
    }
    if (!empty($_GET["psvita"])) {
        $sql .= " tt.platform LIKE '%PSVITA%' OR";
    }
    if (!empty($_GET["psvr"])) {
        $sql .= " tt.platform LIKE '%PSVR' OR tt.platform LIKE '%PSVR,%' OR";
    }
    if (!empty($_GET["psvr2"])) {
        $sql .= " tt.platform LIKE '%PSVR2%' OR";
    }

    // Remove " OR"
    $sql = substr($sql, 0, -3);
    $sql .= ")";
}
$query = $database->prepare($sql);
$query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
if (!empty($_GET["search"]) || $sort == "search") {
    $search = $_GET["search"] ?? "";
    $query->bindParam(":search", $search, PDO::PARAM_STR);
}
$query->execute();
$total_pages = $query->fetchColumn();

$page = max(isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1, 1);
$limit = 50;
$offset = ($page - 1) * $limit;

$title = $player["online_id"] . "'s Trophy Progress ~ PSN 100%";
require_once("header.php");
?>

<main class="container">
    <?php
    require_once("player_header.php");
    ?>

    <div class="p-3">
        <div class="row">
            <div class="col-12 col-lg-3">
            </div>

            <div class="col-12 col-lg-6 mb-3 text-center">
                <div class="btn-group">
                    <a class="btn btn-primary active" href="/player/<?= $player["online_id"]; ?>">Games</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/log">Log</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/advisor">Trophy Advisor</a>
                    <a class="btn btn-outline-primary" href="/game?sort=completion&player=<?= $player["online_id"]; ?>">Game Advisor</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/random">Random Games</a>
                </div>
            </div>

            <div class="col-12 col-lg-3 mb-3">
                <form>
                    <div class="input-group d-flex justify-content-end">
                        <input type="text" name="search" class="form-control rounded-start" placeholder="Game..." value="<?= $_GET["search"]; ?>" aria-label="Text input to search for a game within the player">

                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Filter</button>
                        <ul class="dropdown-menu p-2">
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= (!empty($_GET["uncompleted"]) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterUncompletedGames" name="uncompleted">
                                    <label class="form-check-label" for="filterUncompletedGames">
                                        Uncompleted Games
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= (!empty($_GET["ps3"]) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPS3" name="ps3">
                                    <label class="form-check-label" for="filterPS3">
                                        PS3
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= (!empty($_GET["ps4"]) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPS4" name="ps4">
                                    <label class="form-check-label" for="filterPS4">
                                        PS4
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= (!empty($_GET["ps5"]) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPS5" name="ps5">
                                    <label class="form-check-label" for="filterPS5">
                                        PS5
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= (!empty($_GET["psvita"]) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPSVITA" name="psvita">
                                    <label class="form-check-label" for="filterPSVITA">
                                        PSVITA
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= (!empty($_GET["psvr"]) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPSVR" name="psvr">
                                    <label class="form-check-label" for="filterPSVR">
                                        PSVR
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= (!empty($_GET["psvr2"]) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPSVR2" name="psvr2">
                                    <label class="form-check-label" for="filterPSVR2">
                                        PSVR2
                                    </label>
                                </div>
                            </li>
                        </ul>

                        <select class="form-select" name="sort" onChange="this.form.submit()">
                            <option disabled>Sort by...</option>
                            <option value="search"<?= ($sort == "search" ? " selected" : ""); ?>>Best Match</option>
                            <option value="date"<?= ($sort == "date" ? " selected" : ""); ?>>Date</option>
                            <option value="max-rarity"<?= ($sort == "max-rarity" ? " selected" : ""); ?>>Max Rarity</option>
                            <option value="name"<?= ($sort == "name" ? " selected" : ""); ?>>Name</option>
                            <option value="rarity"<?= ($sort == "rarity" ? " selected" : ""); ?>>Rarity</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mb-3">
            <div class="bg-body-tertiary p-3 rounded">
                <div class="table-responsive-xxl">
                    <table class="table">
                        <thead>
                            <tr class="text-uppercase">
                                <th scope="col">Game</th>
                                <th scope="col" class="text-center">Platform</th>
                                <th scope="col" class="text-center">Progress</th>
                                <th scope="col" class="text-center">Rarity Points</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            if ($player["status"] == 3) {
                                ?>
                                <tr>
                                    <td colspan="4" class="text-center"><h3>This player seems to have a <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.playstation.com/en-us/support/account/privacy-settings-psn/">private</a> profile.</h3></td>
                                </tr>
                                <?php
                            } else {
                                $sql = "SELECT tt.id,
                                            tt.np_communication_id,
                                            tt.name,
                                            tt.icon_url,
                                            tt.platform,
                                            tt.status,
                                            tt.rarity_points AS max_rarity_points,
                                            ttp.bronze,
                                            ttp.silver,
                                            ttp.gold,
                                            ttp.platinum,
                                            ttp.progress,
                                            ttp.last_updated_date,
                                            ttp.rarity_points";
                                if (!empty($_GET["search"]) || $sort == "search") {
                                    $sql .= ", MATCH(tt.name) AGAINST (:search) AS score";
                                }
                                $sql .= " FROM trophy_title_player ttp
                                            JOIN trophy_title tt USING (np_communication_id)
                                        WHERE  ttp.account_id = :account_id
                                            AND tt.status != 2 ";
                                if (!empty($_GET["search"] || $sort == "search")) {
                                    $sql .= " AND (MATCH(tt.name) AGAINST (:search))";
                                }
                                if (!empty($_GET["uncompleted"])) {
                                    $sql .= " AND ttp.progress != 100";
                                }
                                if (!empty($_GET["ps3"]) || !empty($_GET["ps4"]) || !empty($_GET["ps5"]) || !empty($_GET["psvita"]) || !empty($_GET["psvr"]) || !empty($_GET["psvr2"])) {
                                    $sql .= " AND (";
                                    if (!empty($_GET["ps3"])) {
                                        $sql .= " tt.platform LIKE '%PS3%' OR";
                                    }
                                    if (!empty($_GET["ps4"])) {
                                        $sql .= " tt.platform LIKE '%PS4%' OR";
                                    }
                                    if (!empty($_GET["ps5"])) {
                                        $sql .= " tt.platform LIKE '%PS5%' OR";
                                    }
                                    if (!empty($_GET["psvita"])) {
                                        $sql .= " tt.platform LIKE '%PSVITA%' OR";
                                    }
                                    if (!empty($_GET["psvr"])) {
                                        $sql .= " tt.platform LIKE '%PSVR' OR tt.platform LIKE '%PSVR,%' OR";
                                    }
                                    if (!empty($_GET["psvr2"])) {
                                        $sql .= " tt.platform LIKE '%PSVR2%' OR";
                                    }
                                
                                    // Remove " OR"
                                    $sql = substr($sql, 0, -3);
                                    $sql .= ")";
                                }
                                $sql .= " GROUP BY np_communication_id";
                                switch ($sort) {
                                    case "max-rarity":
                                        $sql .= " ORDER BY max_rarity_points DESC, `name`";
                                        break;
                                    case "name":
                                        $sql .= " ORDER BY `name`";
                                        break;
                                    case "rarity":
                                        $sql .= " ORDER BY rarity_points DESC, `name`";
                                        break;
                                    case "search":
                                        $sql .= " ORDER BY score DESC";
                                        break;
                                    default: // date
                                        $sql .= " ORDER BY last_updated_date DESC";
                                }
                                $sql .= " LIMIT :offset, :limit ";
                                $query = $database->prepare($sql);
                                if (!empty($_GET["search"]) || $sort == "search") {
                                    $search = $_GET["search"] ?? "";
                                    $query->bindParam(":search", $search, PDO::PARAM_STR);
                                }
                                $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                                $query->bindParam(":offset", $offset, PDO::PARAM_INT);
                                $query->bindParam(":limit", $limit, PDO::PARAM_INT);
                                $query->execute();
                                $playerGames = $query->fetchAll();

                                foreach ($playerGames as $playerGame) {
                                    $trClass = "";
                                    if ($playerGame["status"] == 1) {
                                        $trClass = " class=\"table-warning\" title=\"This game is delisted, no trophies will be accounted for on any leaderboard.\"";
                                    } elseif ($playerGame["status"] == 3) {
                                        $trClass = " class=\"table-warning\" title=\"This game is obsolete, no trophies will be accounted for on any leaderboard.\"";
                                    } elseif ($playerGame["status"] == 4) {
                                        $trClass = " class=\"table-warning\" title=\"This game is delisted &amp; obsolete, no trophies will be accounted for on any leaderboard.\"";
                                    } elseif ($playerGame["progress"] == 100) {
                                        $trClass = " class=\"table-success\"";
                                    } ?>
                                    <tr<?= $trClass; ?>>
                                        <td scope="row">
                                            <div class="hstack gap-3">
                                                <img src="/img/title/<?= ($playerGame["icon_url"] == ".png") ? ((str_contains($playerGame["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $playerGame["icon_url"]; ?>" alt="" style="background: linear-gradient(to bottom,#145EBB 0,#142788 100%);" width="100" />

                                                <div class="vstack">
                                                    <span>
                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $playerGame["id"] ."-". slugify($playerGame["name"]); ?>/<?= $player["online_id"]; ?>">
                                                            <?= htmlentities($playerGame["name"]); ?>
                                                        </a>
                                                    </span>

                                                    <span id="<?= $playerGame["id"]; ?>"></span>
                                                    <script>
                                                        document.getElementById("<?= $playerGame["id"]; ?>").innerHTML = new Date('<?= $playerGame["last_updated_date"]; ?> UTC').toLocaleString('sv-SE');
                                                    </script>

                                                    <?php
                                                    if ($playerGame["progress"] == 100) {
                                                        $query = $database->prepare("SELECT Min(earned_date) AS first_trophy,
                                                                Max(earned_date) AS last_trophy
                                                            FROM   trophy_earned
                                                            WHERE  np_communication_id = :np_communication_id
                                                                AND account_id = :account_id ");
                                                        $query->bindParam(":np_communication_id", $playerGame["np_communication_id"], PDO::PARAM_STR);
                                                        $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                                                        $query->execute();
                                                        $completionDates = $query->fetch();
                                                        if (isset($completionDates["first_trophy"]) && isset($completionDates["last_trophy"])) {
                                                            $datetime1 = date_create($completionDates["first_trophy"]);
                                                            $datetime2 = date_create($completionDates["last_trophy"]);
                                                            $completionTimes = explode(", ", date_diff($datetime1, $datetime2)->format("%y years, %m months, %d days, %h hours, %i minutes, %s seconds"));
                                                        }
                                                        $completionTimes = $completionTimes ?? [];

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

                                                        echo "<br>";
                                                        if ($first >= 0 && $second >= 0) {
                                                            echo "Completed in ". $completionTimes[$first] .", ". $completionTimes[$second];
                                                        } elseif ($first >= 0 && $second == -1) {
                                                            echo "Completed in ". $completionTimes[$first];
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php
                                            foreach (explode(",", $playerGame["platform"]) as $platform) {
                                                echo "<span class=\"badge rounded-pill text-bg-primary p-2 me-1 mb-1\">". $platform ."</span> ";
                                            }
                                            ?>
                                        </td>
                                        <td class="align-middle text-center" style="white-space: nowrap; width: 10rem;">
                                            <div class="vstack gap-1">
                                                <div>
                                                    <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $playerGame["platinum"]; ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $playerGame["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $playerGame["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $playerGame["bronze"]; ?></span>
                                                </div>

                                                <div>
                                                    <div class="progress" role="progressbar" aria-label="Player game progress" aria-valuenow="<?= $playerGame["progress"]; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <div class="progress-bar<?= ($playerGame["status"] != 0 ? " bg-warning" : ($playerGame["progress"] == 100 ? " bg-success" : "")) ?>" style="width: <?= $playerGame["progress"]; ?>%"><?= $playerGame["progress"]; ?>%</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php
                                            if ($player["status"] == 0 && $playerGame["status"] == 0) {
                                                echo number_format($playerGame["rarity_points"]);
                                                if ($playerGame["progress"] != 100) {
                                                    echo "/". number_format($playerGame["max_rarity_points"]);
                                                }
                                            } elseif ($playerGame["status"] == 1) {
                                                echo "<span class=\"badge rounded-pill text-bg-warning\">Delisted</span>";
                                            } elseif ($playerGame["status"] == 3) {
                                                echo "<span class=\"badge rounded-pill text-bg-warning\">Obsolete</span>";
                                            } elseif ($playerGame["status"] == 4) {
                                                echo "<span class=\"badge rounded-pill text-bg-warning\">Delisted &amp; Obsolete</span>";
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
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
                <?= ($total_pages == 0 ? "0" : $offset + 1); ?>-<?= min($offset + $limit, $total_pages); ?> of <?= number_format($total_pages); ?>
            </p>
        </div>
        <div class="col-12">
            <nav aria-label="Player games navigation">
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
