<?php
$title = "Games ~ PSN 100%";
require_once("header.php");

$url = $_SERVER["REQUEST_URI"];
$url_parts = parse_url($url);
// If URL doesn't have a query string.
if (isset($url_parts["query"])) { // Avoid 'Undefined index: query'
    parse_str($url_parts["query"], $params);
} else {
    $params = array();
}

$sort = (!empty($_GET["sort"]) ? $_GET["sort"] : (!empty($_GET["search"]) ? "search" : "added"));
$player = $_GET["player"] ?? "";

$sql = "";
switch ($sort) {
    case "completion":
        $sql = "SELECT COUNT(*) FROM trophy_title tt";
        $sql .= " LEFT JOIN trophy_title_player ttp ON ttp.np_communication_id = tt.np_communication_id AND ttp.progress = 100 AND ttp.account_id = (SELECT account_id FROM player WHERE online_id = :online_id)";
        $sql .= " WHERE tt.status = 0 AND (tt.bronze + tt.silver + tt.gold + tt.platinum) != 0";
        break;
    case "rarity":
        $sql = "SELECT COUNT(*) FROM trophy_title tt";
        $sql .= " LEFT JOIN trophy_title_player ttp ON ttp.np_communication_id = tt.np_communication_id AND ttp.progress = 100 AND ttp.account_id = (SELECT account_id FROM player WHERE online_id = :online_id)";
        $sql .= " WHERE tt.status = 0";
        break;
    default: // added, owners & search (if no sort)
        $sql = "SELECT COUNT(*) FROM trophy_title tt";
        $sql .= " LEFT JOIN trophy_title_player ttp ON ttp.np_communication_id = tt.np_communication_id AND ttp.progress = 100 AND ttp.account_id = (SELECT account_id FROM player WHERE online_id = :online_id)";
        $sql .= " WHERE tt.status != 2";
}
if (!empty($_GET["search"]) || $sort == "search") {
    $sql .= " AND (MATCH(tt.name) AGAINST (:search)) > 0";
}
if (!empty($_GET["filter"])) {
    $sql .= " AND ttp.progress IS NULL";
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
$query->bindParam(":online_id", $player, PDO::PARAM_STR);
if (!empty($_GET["search"]) || $sort == "search") {
    $search = $_GET["search"] ?? "";
    $query->bindParam(":search", $search, PDO::PARAM_STR);
}
$query->execute();
$total_pages = $query->fetchColumn();

$page = max(isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1, 1);
$limit = 40;
$offset = ($page - 1) * $limit;
?>

<main class="container">
    <div class="row">
        <div class="col-8">
            <h1>Games</h1>
        </div>

        <div class="col-4">
            <form>
                <div class="input-group d-flex justify-content-end">
                    <input type="hidden" name="page" value="<?= $_GET["page"]; ?>">
                    <input type="hidden" name="search" value="<?= $_GET["search"]; ?>">
                    <input type="text" name="player" class="form-control rounded-start" maxlength="16" placeholder="View as player..." value="<?= $player; ?>" aria-label="Text input to show completed games for specified player">
                    

                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Filter</button>
                    <ul class="dropdown-menu p-2">
                        <li>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"<?= (!empty($_GET["filter"]) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterCompletedGames" name="filter">
                                <label class="form-check-label" for="filterCompletedGames">
                                    Hide Completed Games
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
                        <option value="added"<?= ($sort == "added" ? " selected" : ""); ?>>Added to Site</option>
                        <option value="search"<?= ($sort == "search" ? " selected" : ""); ?>>Best Match</option>
                        <option value="completion"<?= ($sort == "completion" ? " selected" : ""); ?>>Completion Rate</option>
                        <option value="owners"<?= ($sort == "owners" ? " selected" : ""); ?>>Owners</option>
                        <option value="rarity"<?= ($sort == "rarity" ? " selected" : ""); ?>>Rarity Points</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <?php
        $sql = "SELECT tt.np_communication_id, tt.id, tt.name, tt.status, tt.icon_url, tt.platform, tt.owners, tt.difficulty, tt.platinum, tt.gold, tt.silver, tt.bronze, tt.rarity_points, ttp.progress";
        if (!empty($_GET["search"]) || $sort == "search") {
             $sql .= ", MATCH(tt.name) AGAINST (:search) AS score";
        }
        $sql .= " FROM trophy_title tt";
        $sql .= " LEFT JOIN trophy_title_player ttp ON ttp.np_communication_id = tt.np_communication_id AND ttp.progress = 100 AND ttp.account_id = (SELECT account_id FROM player WHERE online_id = :online_id)";
        $sql .= " WHERE";
        switch ($sort) {
            case "completion":
                $sql .= " tt.status = 0 AND (tt.bronze + tt.silver + tt.gold + tt.platinum) != 0";
                break;
            case "rarity":
                $sql .= " tt.status = 0";
                break;
            default: // added, owners, search (if no sort)
                $sql .= " tt.status != 2";
                break;
        }
        if (!empty($_GET["search"] || $sort == "search")) {
            $sql .= " AND (MATCH(tt.name) AGAINST (:search))";
        }
        if (!empty($_GET["filter"])) {
            $sql .= " AND ttp.progress IS NULL";
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
        switch ($sort) {
            case "completion":
                $sql .= " ORDER BY difficulty DESC, owners DESC, `name`";
                break;
            case "owners":
                $sql .= " ORDER BY owners DESC, `name`";
                break;
            case "rarity":
                $sql .= " ORDER BY rarity_points DESC, owners DESC, `name`";
                break;
            case "search":
                $sql .= " ORDER BY score DESC";
                break;
            default: // added
                $sql .= " ORDER BY id DESC";
        }
        $sql .= " LIMIT :offset, :limit";
        $games = $database->prepare($sql);
        if (!empty($_GET["search"]) || $sort == "search") {
            $search = $_GET["search"] ?? "";
            $games->bindParam(":search", $search, PDO::PARAM_STR);
        }
        $games->bindParam(":online_id", $player, PDO::PARAM_STR);
        $games->bindParam(":offset", $offset, PDO::PARAM_INT);
        $games->bindParam(":limit", $limit, PDO::PARAM_INT);
        $games->execute();
        $games = $games->fetchAll();

        foreach ($games as $game) {
            $divBgColor = "bg-body-tertiary";
            if ($game["status"] == 1) {
                $divBgColor = "bg-warning-subtle";
            } elseif ($game["status"] == 3) {
                $divBgColor = "bg-warning-subtle";
            } elseif ($game["status"] == 4) {
                $divBgColor = "bg-warning-subtle";
            } elseif ($game["progress"] == 100) {
                $divBgColor = "bg-success-subtle";
            }

            $playerCompleted = false;
            if ($game["progress"] == 100) {
                $playerCompleted = true;
            }

            $gameLink = $game["id"] ."-". slugify($game["name"]);
            if (isset($player)) {
                $gameLink .= "/". $player;
            }
            ?>
            <div class="col-md-6 col-xl-3">
                <div class="<?= $divBgColor; ?> p-3 rounded mb-3 text-center vstack gap-1">
                    <div class="vstack gap-1">
                        <!-- image, platforms and status -->
                        <div>
                            <div class="card">
                                <div class="d-flex justify-content-center align-items-center" style="min-height: 11.5rem;">
                                    <a href="/game/<?= $gameLink; ?>">
                                        <img class="card-img object-fit-scale" style="height: 11.5rem;" src="/img/title/<?= ($game["icon_url"] == ".png") ? ((str_contains($game["platform"], "PS5") || str_contains($game["platform"], "PSVR2")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $game["icon_url"]; ?>" alt="<?= htmlentities($game["name"]); ?>">
                                        <div class="card-img-overlay d-flex align-items-end p-2">
                                            <?php
                                            foreach (explode(",", $game["platform"]) as $platform) {
                                                echo "<span class=\"badge rounded-pill text-bg-primary p-2 me-1\">". $platform ."</span> ";
                                            }
                                            ?>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- owners & cr -->
                        <div>
                            <?= number_format($game["owners"]); ?> <?= ($game["owners"] > 1 ? 'owners' : 'owner'); ?> (<?= $game["difficulty"]; ?>%)
                        </div>

                        <!-- name -->
                        <div class="text-center">
                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $gameLink; ?>">
                                <?= htmlentities($game["name"]); ?>
                            </a>
                        </div>

                        <div>
                            <hr class="m-0">
                        </div>

                        <!-- trophies -->
                        <div>
                            <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $game["platinum"]; ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $game["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $game["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $game["bronze"]; ?></span>
                        </div>

                        <!-- rarity points / status -->
                        <div>
                            <?php
                            if ($game["status"] == 0) {
                                echo number_format($game["rarity_points"]) ." Rarity Points";
                            } elseif ($game["status"] == 1) {
                                echo "<span class='badge rounded-pill text-bg-warning' title='This game is delisted, no trophies will be accounted for on any leaderboard.'>Delisted</span>";
                            } elseif ($game["status"] == 3) {
                                echo "<span class='badge rounded-pill text-bg-warning' title='This game is obsolete, no trophies will be accounted for on any leaderboard.'>Obsolete</span>";
                            } elseif ($game["status"] == 4) {
                                echo "<span class='badge rounded-pill text-bg-warning' title='This game is delisted &amp; obsolete, no trophies will be accounted for on any leaderboard.'>Delisted &amp; Obsolete</span>";
                            }

                            if ($playerCompleted) {
                                echo " <span class='badge rounded-pill text-bg-success' title='Player have completed this game to 100%!'>Completed!</span>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>
    </div>

    <div class="row">
        <div class="col-12">
            <p class="text-center">
                <?= ($total_pages == 0 ? "0" : $offset + 1); ?>-<?= min($offset + $limit, $total_pages); ?> of <?= number_format($total_pages); ?>
            </p>
        </div>
        <div class="col-12">
            <nav aria-label="Games page navigation">
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
