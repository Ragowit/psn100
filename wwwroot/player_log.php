<?php
if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

$query = $database->prepare("SELECT * FROM player WHERE account_id = :account_id");
$query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
$query->execute();
$player = $query->fetch();

$url = $_SERVER["REQUEST_URI"];
$url_parts = parse_url($url);
// If URL doesn't have a query string.
if (isset($url_parts["query"])) { // Avoid 'Undefined index: query'
    parse_str($url_parts["query"], $params);
} else {
    $params = array();
}

$sort = (!empty($_GET["sort"]) ? $_GET["sort"] : "date");

if ($player["status"] == 1 || $player["status"] == 3) {
    $total_pages = 0;
} else {
    $sql = "SELECT COUNT(*) FROM trophy_earned te
        JOIN trophy_title tt USING (np_communication_id)
        WHERE tt.status != 2 AND te.account_id = :account_id";
    if (!empty($_GET["pc"]) || !empty($_GET["ps3"]) || !empty($_GET["ps4"]) || !empty($_GET["ps5"]) || !empty($_GET["psvita"]) || !empty($_GET["psvr"]) || !empty($_GET["psvr2"])) {
        $sql .= " AND (";
        if (!empty($_GET["pc"])) {
            $sql .= " tt.platform LIKE '%PC%' OR";
        }
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
    $query->execute();
    $total_pages = $query->fetchColumn();
}

$page = max(isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1, 1);
$limit = 50;
$offset = ($page - 1) * $limit;

$title = $player["online_id"] . "'s Trophy Log ~ PSN 100%";
require_once("header.php");
?>

<main class="container">
    <?php
    require_once("player_header.php");
    ?>

    <div class="p-3">
        <div class="row">
            <div class="col-12 col-lg-3">
                <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover text-danger" href="/player/<?= $player["online_id"]; ?>/report">Report Player</a>
            </div>

            <div class="col-12 col-lg-6 mb-3 text-center">
                <div class="btn-group">
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>">Games</a>
                    <a class="btn btn-primary active" href="/player/<?= $player["online_id"]; ?>/log">Log</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/advisor">Trophy Advisor</a>
                    <a class="btn btn-outline-primary" href="/game?sort=completion&player=<?= $player["online_id"]; ?>">Game Advisor</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/random">Random Games</a>
                </div>
            </div>

            <div class="col-12 col-lg-3 mb-3">
                <form>
                    <div class="input-group d-flex justify-content-end">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Filter</button>
                        <ul class="dropdown-menu p-2">
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= (!empty($_GET["pc"]) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPC" name="pc">
                                    <label class="form-check-label" for="filterPC">
                                        PC
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
                            <option value="date"<?= ($sort == "date" ? " selected" : ""); ?>>Date</option>
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
                                <th scope="col" class="text-center">Game</th>
                                <th scope="col">Trophy</th>
                                <th scope="col" class="text-center">Platform</th>
                                <th scope="col" class="text-center">Rarity</th>
                                <th scope="col" class="text-center">Type</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            if ($player["status"] == 1) {
                                ?>
                                <tr>
                                    <td colspan="5" class="text-center"><h3>This player have some funny looking trophy data. This doesn't necessarily means cheating, but all data from this player will not be in any of the site statistics or leaderboards. <a href="https://github.com/Ragowit/psn100/issues?q=label%3Acheater+<?= $player["online_id"]; ?>+OR+<?= $player["account_id"]; ?>">Dispute</a>?</h3></td>
                                </tr>
                                <?php
                            } elseif ($player["status"] == 3) {
                                ?>
                                <tr>
                                    <td colspan="5" class="text-center"><h3>This player seems to have a <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.playstation.com/en-us/support/account/privacy-settings-psn/">private</a> profile.</h3></td>
                                </tr>
                                <?php
                            } else {
                                $sql = "SELECT
                                        te.*,
                                        t.id AS trophy_id,
                                        t.type AS trophy_type,
                                        t.name AS trophy_name,
                                        t.detail AS trophy_detail,
                                        t.icon_url AS trophy_icon,
                                        t.rarity_percent,
                                        t.status AS trophy_status,
                                        t.progress_target_value,
                                        t.reward_name,
                                        t.reward_image_url,
                                        tt.id AS game_id,
                                        tt.name AS game_name,
                                        tt.status AS game_status,
                                        tt.icon_url AS game_icon,
                                        tt.platform
                                    FROM
                                        trophy_earned te
                                    LEFT JOIN trophy t USING(
                                            np_communication_id,
                                            order_id
                                        )
                                    LEFT JOIN trophy_title tt USING(np_communication_id)
                                    WHERE
                                        tt.status != 2 AND te.account_id = :account_id AND te.earned = 1";
                                if (!empty($_GET["pc"]) || !empty($_GET["ps3"]) || !empty($_GET["ps4"]) || !empty($_GET["ps5"]) || !empty($_GET["psvita"]) || !empty($_GET["psvr"]) || !empty($_GET["psvr2"])) {
                                    $sql .= " AND (";
                                    if (!empty($_GET["pc"])) {
                                        $sql .= " tt.platform LIKE '%PC%' OR";
                                    }
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
                                    case "rarity":
                                        $sql .= " ORDER BY t.rarity_percent, te.earned_date";
                                        break;
                                    default: // date
                                        $sql .= " ORDER BY te.earned_date DESC";
                                }
                                $sql .= " LIMIT :offset, :limit";

                                $trophies = $database->prepare($sql);
                                $trophies->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                                $trophies->bindParam(":offset", $offset, PDO::PARAM_INT);
                                $trophies->bindParam(":limit", $limit, PDO::PARAM_INT);
                                $trophies->execute();

                                while ($trophy = $trophies->fetch()) {
                                    ?>
                                    <tr<?= (($trophy["game_status"] != 0 || $trophy["trophy_status"] != 0) ? " class='table-warning'" : ""); ?>>
                                        <td scope="row" class="text-center align-middle">
                                            <a href="/game/<?= $trophy["game_id"] ."-". slugify($trophy["game_name"]); ?>/<?= $player["online_id"]; ?>">
                                                <img src="/img/title/<?= ($trophy["game_icon"] == ".png") ? ((str_contains($trophy["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $trophy["game_icon"]; ?>" alt="<?= $trophy["game_name"]; ?>" title="<?= $trophy["game_name"]; ?>" style="width: 10rem;" />
                                            </a>
                                        </td>
                                        <td class="align-middle">
                                            <div class="hstack gap-3">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <a href="/trophy/<?= $trophy["trophy_id"] ."-". slugify($trophy["trophy_name"]); ?>/<?= $player["online_id"]; ?>">
                                                        <img src="/img/trophy/<?= ($trophy["trophy_icon"] == ".png") ? ((str_contains($trophy["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-trophy.png") : $trophy["trophy_icon"]; ?>" alt="<?= $trophy["trophy_name"]; ?>" title="<?= $trophy["trophy_name"]; ?>" style="width: 5rem;" />
                                                    </a>
                                                </div>

                                                <div>
                                                    <div class="vstack">
                                                        <span>
                                                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/trophy/<?= $trophy["trophy_id"] ."-". slugify($trophy["trophy_name"]); ?>">
                                                                <b><?= htmlentities($trophy["trophy_name"]); ?></b>
                                                            </a>
                                                        </span>
                                                        <?= nl2br(htmlentities($trophy["trophy_detail"], ENT_QUOTES, "UTF-8")); ?>
                                                        <?php
                                                        if ($trophy["progress_target_value"] != null) {
                                                            echo "<br><b>0/". $trophy["progress_target_value"] ."</b>";
                                                        }

                                                        if ($trophy["reward_name"] != null && $trophy["reward_image_url"] != null) {
                                                            echo "<br>Reward: <a href='/img/reward/". $trophy["reward_image_url"] ."'>". $trophy["reward_name"] ."</a>";
                                                        }
                                                        ?>
                                                        <div>
                                                            <span class="badge rounded-pill text-bg-success" id="<?= $trophy["trophy_id"]; ?>"></span>
                                                            <script>
                                                                document.getElementById("<?= $trophy["trophy_id"]; ?>").innerHTML = 'Earned ' + new Date('<?= $trophy["earned_date"]; ?> UTC').toLocaleString('sv-SE');
                                                            </script>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <div class="vstack gap-1">
                                                <?php
                                                foreach (explode(",", $trophy["platform"]) as $platform) {
                                                    echo "<span class=\"badge rounded-pill text-bg-primary p-2\">". $platform ."</span> ";
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php
                                            if ($trophy["trophy_status"] == 1) {
                                                echo "<span class='badge rounded-pill text-bg-warning p-2'>Unobtainable</span>";
                                            } elseif ($trophy["rarity_percent"] <= 0.02) {
                                                echo "<span class='trophy-legendary'>". $trophy["rarity_percent"] ."%<br>Legendary</span>";
                                            } elseif ($trophy["rarity_percent"] <= 0.2) {
                                                echo "<span class='trophy-epic'>". $trophy["rarity_percent"] ."%<br>Epic</span>";
                                            } elseif ($trophy["rarity_percent"] <= 2) {
                                                echo "<span class='trophy-rare'>". $trophy["rarity_percent"] ."%<br>Rare</span>";
                                            } elseif ($trophy["rarity_percent"] <= 20) {
                                                echo "<span class='trophy-uncommon'>". $trophy["rarity_percent"] ."%<br>Uncommon</span>";
                                            } else {
                                                echo "<span class='trophy-common'>". $trophy["rarity_percent"] ."%<br>Common</span>";
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <img src="/img/trophy-<?= $trophy["trophy_type"]; ?>.svg" alt="<?= ucfirst($trophy["trophy_type"]); ?>" title="<?= ucfirst($trophy["trophy_type"]); ?>" height="50" />
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
            <nav aria-label="Player log navigation">
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
