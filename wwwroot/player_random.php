<?php
if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

$query = $database->prepare("SELECT * FROM player WHERE account_id = :account_id");
$query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
$query->execute();
$player = $query->fetch();

$title = $player["online_id"] . "'s Random Games ~ PSN 100%";
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
                <h5><a href="/player/<?= $player["online_id"]; ?>/advisor">Trophy Advisor</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/game?sort=completion&player=<?= $player["online_id"]; ?>">Game Advisor</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>/timeline">Timeline</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5>Random Games</h5>
            </div>
        </div>

        <?php
        if ($player["status"] == 3) {
            ?>
            <div class="row">
                <div class="col-12 text-center">
                    <h3>This player seems to have a <a href="https://www.playstation.com/en-us/support/account/privacy-settings-psn/">private</a> profile.</h3>
                </div>
            </div>
            <?php
        } else {
            ?>
            <div class="row">
                <div class="col-12 text-center">
                    <h3>Here are five random games that this player haven't got to 100%</h3>
                </div>

                <div class="col-12">
                    <table class="table table-responsive table-striped">
                        <tr class="table-primary">
                            <th scope="col">Icon</th>
                            <th scope="col" width="100%">Game Title</th>
                            <th scope="col">Platform</th>
                            <th scope="col" class="text-center"><img src="/img/playstation/trophies.png" alt="Trophies" width="50" /></th>
                        </tr>
                    
                        <?php
                        $query = $database->prepare("SELECT tt.id, tt.np_communication_id, tt.name, tt.icon_url, tt.platform, tt.bronze, tt.silver, tt.gold, tt.platinum, tt.owners, tt.difficulty
                            FROM trophy_title tt
                            LEFT JOIN trophy_title_player ttp ON ttp.np_communication_id = tt.np_communication_id AND ttp.account_id = :account_id
                            WHERE tt.status = 0 AND (ttp.progress != 100 OR ttp.progress IS NULL) ORDER BY RAND() LIMIT 5");
                        $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                        $query->execute();
                        $randomGames = $query->fetchAll();
                        foreach ($randomGames as $game) {
                            $query = $database->prepare("SELECT Ifnull(Sum(rarity_point), 0) 
                                FROM   trophy 
                                WHERE  np_communication_id = :np_communication_id 
                                    AND status = 0 ");
                            $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                            $query->execute();
                            $rarityPoints = $query->fetchColumn();
                            ?>
                            <tr>
                                <td scope="row">
                                    <a href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?>/<?= $player["online_id"]; ?>">
                                        <img src="/img/title/<?= ($game["icon_url"] == ".png") ? ((str_contains($game["platform"], "PS5") || str_contains($game["platform"], "PSVR2")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $game["icon_url"]; ?>" alt="" style="background: linear-gradient(to bottom,#145EBB 0,#142788 100%);" width="100" />
                                    </a>
                                </td>
                                <td>
                                    <a href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?>/<?= $player["online_id"]; ?>">
                                        <?= htmlentities($game["name"]); ?>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <?php
                                    foreach (explode(",", $game["platform"]) as $platform) {
                                        echo "<span class=\"badge badge-pill badge-primary\">". $platform ."</span> ";
                                    } ?>
                                </td>
                                <td class="text-center" style="white-space: nowrap;">
                                    <?= $game["bronze"]; ?> <img src="/img/playstation/bronze.png" alt="Bronze" width="24" />
                                    <?= $game["silver"]; ?> <img src="/img/playstation/silver.png" alt="Silver" width="24" />
                                    <?= $game["gold"]; ?> <img src="/img/playstation/gold.png" alt="Gold" width="24" />
                                    <?= $game["platinum"]; ?> <img src="/img/playstation/platinum.png" alt="Platinum" width="24" />
                                    <br>
                                    <?= $game["owners"]; ?> owners<br>
                                    <?= $game["difficulty"]; ?>% Completion Rate<br>
                                    <?= number_format($rarityPoints); ?> Rarity Points
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </table>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
</main>
<?php
require_once("footer.php");
?>
