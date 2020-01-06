<?php
$title = "PSN100.net ~ PlayStation Leaderboards & Trophies";
require_once("header.php");
?>
<main role="main">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1>PSN100</h1>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="alert alert-info" role="alert">
                    <?php
                    $query = $database->prepare("SELECT COUNT(*) FROM player_queue");
                    $query->execute();
                    $queue = $query->fetchColumn();
                    ?>
                    This site is newly launched and currently scanning through the trophy list of <?= $queue; ?> players. The statistics on this site can change drastically until this is complete.
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-6">
                <div class="form-inline justify-content-center">
                    <input type="text" class="form-control" id="player" placeholder="PSN name...">
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
                            $query = $database->prepare("SELECT * FROM trophy_title ORDER BY id DESC LIMIT 10");
                            $query->execute();
                            $games = $query->fetchAll();

                            foreach ($games as $game) {
                                ?>
                                <tr>
                                    <td class="text-center" width="150">
                                        <a href="/game/<?= $game["id"] . "-" . str_replace(" ", "-", $game["name"]); ?>">
                                            <img src="/img/title/<?= $game["icon_url"]; ?>" alt="" width="100" />
                                        </a>
                                        <br>
                                        <?php
                                        foreach (explode(",", $game["platform"]) as $platform) {
                                            echo "<span class=\"badge badge-pill badge-primary\">" . $platform . "</span> ";
                                        } ?>
                                    </td>
                                    <td>
                                        <a href="/game/<?= $game["id"] . "-" . str_replace(" ", "-", $game["name"]); ?>">
                                            <?= $game["name"]; ?>
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
                            $query = $database->prepare("SELECT tt.id, tt.name AS game_name, tt.platform, tg.icon_url, tg.name AS group_name FROM trophy_group tg JOIN trophy_title tt USING (np_communication_id) WHERE tg.group_id != 'default' ORDER BY tg.id DESC LIMIT 10");
                            $query->execute();
                            $dlcs = $query->fetchAll();

                            foreach ($dlcs as $dlc) {
                                ?>
                                <tr>
                                    <td class="text-center" width="150">
                                        <a href="/game/<?= $dlc["id"] . "-" . str_replace(" ", "-", $dlc["game_name"]); ?>">
                                            <img src="/img/group/<?= $dlc["icon_url"]; ?>" alt="" width="100" />
                                        </a>
                                        <br>
                                        <?php
                                        foreach (explode(",", $dlc["platform"]) as $platform) {
                                            echo "<span class=\"badge badge-pill badge-primary\">" . $platform . "</span> ";
                                        } ?>
                                    </td>
                                    <td>
                                        <a href="/game/<?= $dlc["id"] . "-" . str_replace(" ", "-", $dlc["game_name"]); ?>">
                                            <small><?= $dlc["game_name"]; ?></small><br><?= $dlc["group_name"]; ?>
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
                            $query = $database->prepare("SELECT COUNT(DISTINCT account_id) AS count, tt.* FROM trophy_earned te JOIN trophy_title tt USING (np_communication_id) WHERE earned_date >= DATE(NOW()) - INTERVAL 7 DAY GROUP BY np_communication_id ORDER BY count DESC LIMIT 10");
                            $query->execute();
                            $popular_games = $query->fetchAll();

                            foreach ($popular_games as $popular_game) {
                                ?>
                                <tr>
                                    <td class="text-center" width="150">
                                        <a href="/game/<?= $popular_game["id"] . "-" . str_replace(" ", "-", $popular_game["name"]); ?>">
                                            <img src="/img/title/<?= $popular_game["icon_url"]; ?>" alt="" width="100" />
                                        </a>
                                        <br>
                                        <?php
                                        foreach (explode(",", $popular_game["platform"]) as $platform) {
                                            echo "<span class=\"badge badge-pill badge-primary\">" . $platform . "</span> ";
                                        } ?>
                                    </td>
                                    <td>
                                        <a href="/game/<?= $popular_game["id"] . "-" . str_replace(" ", "-", $popular_game["name"]); ?>">
                                            <?= $popular_game["name"]; ?>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <?= $popular_game["count"]; ?> Players
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
