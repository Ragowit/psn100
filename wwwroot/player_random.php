<?php
if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

$title = $player["online_id"] . "'s Random Games ~ PSN 100%";
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
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/log">Log</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/advisor">Trophy Advisor</a>
                    <a class="btn btn-outline-primary" href="/game?sort=completion&player=<?= $player["online_id"]; ?>">Game Advisor</a>
                    <a class="btn btn-primary active" href="/player/<?= $player["online_id"]; ?>/random">Random Games</a>
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
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <?php
        if ($player["status"] == 1) {
            ?>
            <div class="col-12 text-center">
                <h3>This player has some funny looking trophy data. This doesn't necessarily mean cheating, but all data from this player will be excluded from site statistics and leaderboards. <a href="https://github.com/Ragowit/psn100/issues?q=label%3Acheater+<?= $player["online_id"]; ?>+OR+<?= $player["account_id"]; ?>">Dispute</a>?</h3>
            </div>
            <?php
        } elseif ($player["status"] == 3) {
            ?>
            <div class="col-12 text-center">
                <h3>This player seems to have a <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.playstation.com/en-us/support/account/privacy-settings-psn/">private</a> profile.</h3>
            </div>
            <?php
        } else {
            $sql = "SELECT tt.id, tt.np_communication_id, tt.name, tt.icon_url, tt.platform, tt.owners, tt.difficulty, tt.platinum, tt.gold, tt.silver, tt.bronze, tt.rarity_points, ttp.progress
                FROM trophy_title tt
                LEFT JOIN trophy_title_player ttp ON ttp.np_communication_id = tt.np_communication_id AND ttp.account_id = :account_id
                WHERE tt.status = 0 AND (ttp.progress != 100 OR ttp.progress IS NULL)";
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
            $sql .= " ORDER BY RAND() LIMIT 8";
            $games = $database->prepare($sql);
            $games->bindParam(":account_id", $player["account_id"], PDO::PARAM_STR);
            $games->execute();
            $games = $games->fetchAll();

            foreach ($games as $game) {
                $gameLink = $game["id"] ."-". slugify($game["name"]) ."/". $player["online_id"];
                ?>
                <div class="col-md-6 col-xl-3">
                    <div class="bg-body-tertiary p-3 rounded mb-3 text-center vstack gap-1">
                        <div class="vstack gap-1">
                            <!-- image, platforms -->
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

                            <!-- rarity points -->
                            <div>
                                <?php
                                echo number_format($game["rarity_points"]) ." Rarity Points";
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
        ?>
    </div>
</main>

<?php
require_once("footer.php");
?>
