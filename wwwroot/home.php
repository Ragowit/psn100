<?php
$title = "PSN 100% ~ PlayStation Leaderboards & Trophies";
require_once("header.php");
?>

<main class="container">
    <div class="bg-body-tertiary p-3 rounded mb-3">
        <div class="row row-cols">
            <div class="col">
                <div class="input-group mb-1">
                    <input type="text" class="form-control" placeholder="PSN name..." id="player" maxlength="16" aria-label="PSN name..." aria-describedby="player-button">
                    <button class="btn btn-primary" type="button" id="player-button" onclick="addToQueue()">Update</button>
                </div>
            </div>
        </div>
        
        <div class="row justify-content-center" id="queue-result" style="display: none;">
            <div class="col text-center">
                <span id="add-to-queue-result"></span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-8">
            <!-- New Games -->
            <div class="row mb-3">
                <div class="col">
                    <div class="bg-body-tertiary p-3 rounded">
                        <h1>New Games</h1>
                        <div class="row">
                            <?php
                            $query = $database->prepare("SELECT * FROM trophy_title
                                WHERE `status` != 2
                                ORDER BY id DESC
                                LIMIT 8");
                            $query->execute();
                            $games = $query->fetchAll();

                            foreach ($games as $game) {
                                ?>
                                <div class="col-12 col-md-6 col-lg-4 col-xl-3 text-center mb-2">
                                    <div class="vstack gap-1">
                                        <!-- image, platforms and status -->
                                        <div>
                                            <div class="card">
                                                <div class="d-flex justify-content-center align-items-center" style="min-height: 11.5rem;">
                                                    <a href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?>">
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

                                        <!-- trophies -->
                                        <div>
                                            <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $game["platinum"]; ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $game["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $game["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $game["bronze"]; ?></span>
                                        </div>

                                        <!-- name -->
                                        <div class="text-center">
                                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?>">
                                                <?= htmlentities($game["name"]); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New DLCs -->
            <div class="row mb-3">
                <div class="col">
                    <div class="bg-body-tertiary p-3 rounded">
                        <h1>New DLCs</h1>
                        <div class="row">
                            <?php
                            $query = $database->prepare("SELECT tt.id, tt.name AS game_name, tt.platform, tg.icon_url, tg.name AS group_name, tg.group_id, tg.bronze, tg.silver, tg.gold FROM trophy_group tg
                                JOIN trophy_title tt USING (np_communication_id)
                                WHERE tt.status != 2 AND tg.group_id != 'default'
                                ORDER BY tg.id DESC
                                LIMIT 8");
                            $query->execute();
                            $games = $query->fetchAll();

                            foreach ($games as $game) {
                                ?>
                                <div class="col-12 col-md-6 col-lg-4 col-xl-3 text-center mb-2">
                                    <!-- image, platforms and status -->
                                    <div class="vstack gap-1">
                                        <div>
                                            <div class="card">
                                                <div class="d-flex justify-content-center align-items-center" style="min-height: 11.5rem;">
                                                    <a href="/game/<?= $game["id"] ."-". slugify($game["game_name"]); ?>#<?= $game["group_id"]; ?>">
                                                        <img class="card-img object-fit-scale" style="height: 11.5rem;" src="/img/group/<?= ($game["icon_url"] == ".png") ? ((str_contains($game["platform"], "PS5") || str_contains($game["platform"], "PSVR2")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $game["icon_url"]; ?>" alt="<?= htmlentities($game["group_name"]); ?>">
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

                                        <!-- trophies -->
                                        <div>
                                            <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $game["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $game["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $game["bronze"]; ?></span>
                                        </div>

                                        <!-- name -->
                                        <div class="text-center">
                                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $game["id"] ."-". slugify($game["game_name"]); ?>#<?= $game["group_id"]; ?>">
                                                <small><?= htmlentities($game["game_name"]); ?></small><br><?= htmlentities($game["group_name"]); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Games -->
        <div class="col-12 col-lg-4">
            <div class="bg-body-tertiary p-3 rounded">
                <h1>Popular Games</h1>
                <?php
                $query = $database->prepare("SELECT id, icon_url, platform, `name`, recent_players FROM trophy_title
                    WHERE `status` != 2
                    ORDER BY recent_players DESC
                    LIMIT 10");
                $query->execute();
                $games = $query->fetchAll();

                foreach ($games as $game) {
                    ?>
                    <div class="row mb-3">
                        <!-- image -->
                        <div class="col-4">
                            <div class="card">
                                <div class="d-flex justify-content-center align-items-center" style="height: 7rem;">
                                    <a href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?>">
                                        <img class="card-img object-fit-cover" style="height: 7rem;" src="/img/title/<?= ($game["icon_url"] == ".png") ? ((str_contains($game["platform"], "PS5") || str_contains($game["platform"], "PSVR2")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $game["icon_url"]; ?>" alt="<?= htmlentities($game["name"]); ?>">
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- name, platforms and status -->
                        <div class="col-5 d-flex align-items-center">
                            <div>
                                <div class="row">
                                    <div class="col">
                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $game["id"] ."-". slugify($game["name"]); ?>">
                                            <?= htmlentities($game["name"]); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <?php
                                        foreach (explode(",", $game["platform"]) as $platform) {
                                            echo "<span class=\"badge rounded-pill text-bg-primary p-2 mt-2\">". $platform ."</span> ";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Players -->
                        <div class="col-3 text-end d-flex align-items-center">
                            <div class="ms-auto">
                                <span class="fw-bold"><?= number_format($game["recent_players"]); ?></span><br>Players
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
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
            $('#queue-result').show();
        }
    };
    xmlhttp.open("GET", "add_to_queue.php?q=" + player, true);
    xmlhttp.send();

    setInterval(checkQueuePosition, 3000);
}

function checkQueuePosition()
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
    xmlhttp.open("GET", "check_queue_position.php?q=" + player, true);
    xmlhttp.send();
}
</script>



<?php
require_once("footer.php");
?>
