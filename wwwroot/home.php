<?php
$title = "PSN 100% ~ PlayStation Leaderboards & Trophies";
require_once("header.php");
?>
<main role="main">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-6">
                <div class="form-inline justify-content-center">
                    <input type="text" class="form-control" id="player" maxlength="16" placeholder="PSN name...">
                    <button type="button" class="btn btn-primary" id="player-button" onclick="addToQueue()">Update</button>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-6 text-center">
                <p id="add-to-queue-result"></p>
            </div>
        </div>


        <div class="row">
            <div class="col-12 col-xl-4">
                <div class="row">
                    <div class="col-12 text-center">
                        <h2>New games</h2>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 table-responsive">
                        <table class="table table-striped">
                            <?php
                            $query = $database->prepare("SELECT * FROM trophy_title
                                WHERE status != 2
                                ORDER BY id DESC
                                LIMIT 10");
                            $query->execute();
                            $games = $query->fetchAll();

                            foreach ($games as $game) {
                                ?>
                                <tr>
                                    <td class="text-center" width="150">
                                        <a href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?>">
                                            <img src="/img/title/<?= ($game["icon_url"] == ".png") ? ((str_contains($game["platform"], "PS5") || str_contains($game["platform"], "PSVR2")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $game["icon_url"]; ?>" alt="" style="background: linear-gradient(to bottom,#145EBB 0,#142788 100%);" width="100" />
                                        </a>
                                        <br>
                                        <?php
                                        foreach (explode(",", $game["platform"]) as $platform) {
                                            echo "<span class=\"badge badge-pill badge-primary\">" . $platform . "</span> ";
                                        } ?>
                                    </td>
                                    <td>
                                        <a href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?>">
                                            <?= htmlentities($game["name"]); ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="row">
                    <div class="col-12 text-center">
                        <h2>New DLCs</h2>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 table-responsive">
                        <table class="table table-striped">
                            <?php
                            $query = $database->prepare("SELECT tt.id, tt.name AS game_name, tt.platform, tg.icon_url, tg.name AS group_name, tg.group_id FROM trophy_group tg
                                JOIN trophy_title tt USING (np_communication_id)
                                WHERE tt.status != 2 AND tg.group_id != 'default'
                                ORDER BY tg.id DESC
                                LIMIT 10");
                            $query->execute();
                            $dlcs = $query->fetchAll();

                            foreach ($dlcs as $dlc) {
                                ?>
                                <tr>
                                    <td class="text-center" width="150">
                                        <a href="/game/<?= $dlc["id"] ."-". slugify($dlc["game_name"]); ?>#<?= $dlc["group_id"]; ?>">
                                            <img src="/img/group/<?= ($dlc["icon_url"] == ".png") ? ((str_contains($dlc["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $dlc["icon_url"]; ?>" alt="" style="background: linear-gradient(to bottom,#145EBB 0,#142788 100%);" width="100" />
                                        </a>
                                        <br>
                                        <?php
                                        foreach (explode(",", $dlc["platform"]) as $platform) {
                                            echo "<span class=\"badge badge-pill badge-primary\">" . $platform . "</span> ";
                                        } ?>
                                    </td>
                                    <td>
                                        <a href="/game/<?= $dlc["id"] ."-". slugify($dlc["game_name"]); ?>#<?= $dlc["group_id"]; ?>">
                                            <small><?= htmlentities($dlc["game_name"]); ?></small><br><?= htmlentities($dlc["group_name"]); ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="row">
                    <div class="col-12 text-center">
                        <h2>Popular games</h2>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 table-responsive">
                        <table class="table table-striped">
                            <?php
                            $query = $database->prepare("SELECT id, icon_url, platform, name, recent_players FROM trophy_title
                                WHERE status != 2
                                ORDER BY recent_players DESC
                                LIMIT 10");
                            $query->execute();
                            $popularGames = $query->fetchAll();

                            foreach ($popularGames as $popularGame) {
                                ?>
                                <tr>
                                    <td class="text-center" width="150">
                                        <a href="/game/<?= $popularGame["id"] ."-". slugify($popularGame["name"]); ?>">
                                            <img src="/img/title/<?= ($popularGame["icon_url"] == ".png") ? ((str_contains($popularGame["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $popularGame["icon_url"]; ?>" alt="" style="background: linear-gradient(to bottom,#145EBB 0,#142788 100%);" width="100" />
                                        </a>
                                        <br>
                                        <?php
                                        foreach (explode(",", $popularGame["platform"]) as $platform) {
                                            echo "<span class=\"badge badge-pill badge-primary\">". $platform ."</span> ";
                                        } ?>
                                    </td>
                                    <td>
                                        <a href="/game/<?= $popularGame["id"] ."-". slugify($popularGame["name"]); ?>">
                                            <?= htmlentities($popularGame["name"]); ?>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <?= $popularGame["recent_players"]; ?> Players
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
    </div>
</main>

<script>
// Get the input field
var input = document.getElementById("player");

// Execute a function when the user releases a key on the keyboard
input.addEventListener("keyup", function(event)
{
    // Number 13 is the "Enter" key on the keyboard
    if (event.keyCode === 13)
    {
        // Cancel the default action, if needed
        event.preventDefault();
        // Trigger the button element with a click
        document.getElementById("player-button").click();
    }
});

function addToQueue()
{
    var player = document.getElementById("player").value;

    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function()
    {
        if (this.readyState == 4 && this.status == 200)
        {
            document.getElementById("add-to-queue-result").innerHTML = this.responseText;
        }
    };
    xmlhttp.open("GET", "add_to_queue.php?q=" + player, true);
    xmlhttp.send();
}
</script>
<?php
require_once("footer.php");
?>
