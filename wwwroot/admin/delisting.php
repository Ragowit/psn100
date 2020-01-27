<?php
require_once("../init.php");

if (ctype_digit(strval($_POST["game"]))) {
    $gameId = $_POST["game"];

    $database->beginTransaction();
    $query = $database->prepare("UPDATE trophy_title SET status = 1 WHERE id = :game_id");
    $query->bindParam(":game_id", $gameId, PDO::PARAM_INT);
    $query->execute();
    $database->commit();

    // Add all affected players to the queue to recalculate trophy count, level and level progress
    $players = $database->prepare("SELECT online_id FROM player p WHERE EXISTS (SELECT 1 FROM trophy_title_player ttp WHERE ttp.np_communication_id = (SELECT np_communication_id FROM trophy_title WHERE id = :game_id) AND ttp.account_id = p.account_id)");
    $players->bindParam(":game_id", $gameId, PDO::PARAM_INT);
    $players->execute();
    while ($player = $players->fetch()) {
        $query = $database->prepare("INSERT INTO player_queue (online_id, request_time) VALUES (:online_id, '2000-01-01 00:00:00') ON DUPLICATE KEY UPDATE request_time='2000-01-01 00:00:00'"); // An early date like '2000-01-01 00:00:00' makes it first in queue
        $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
        $query->execute();
    }

    $success = "<p>Game ". $gameId ." is now set as delisted. All affected players will be updated soon, and ranks updated the next whole hour.</p>";
}

?>
<!doctype html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Admin ~ Delisted Game</title>
    </head>
    <body>
        <a href="/admin/">Back</a><br><br>
        <form method="post" autocomplete="off">
            Game ID:<br>
            <input type="number" name="game"><br><br>
            <input type="submit" value="Submit">
        </form>

        <?php
        if (isset($success)) {
            echo $success;
        }
        ?>
    </body>
</html>
