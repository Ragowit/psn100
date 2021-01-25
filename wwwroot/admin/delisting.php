<?php
require_once("../init.php");

if (isset($_POST["game"]) && ctype_digit(strval($_POST["game"]))) {
    $gameId = $_POST["game"];
    $status = $_POST["status"];

    $database->beginTransaction();
    $query = $database->prepare("UPDATE trophy_title SET status = :status WHERE id = :game_id");
    $query->bindParam(":status", $status, PDO::PARAM_INT);
    $query->bindParam(":game_id", $gameId, PDO::PARAM_INT);
    $query->execute();
    $database->commit();

    // Add all affected players to the queue to recalculate trophy count, level and level progress
    $query = $database->prepare("INSERT IGNORE
        INTO player_queue(online_id, request_time)
        SELECT
            online_id,
            '2030-12-24 00:00:00'
        FROM
            player p
        WHERE EXISTS
            (
            SELECT
                1
            FROM
                trophy_title_player ttp
            WHERE
                ttp.np_communication_id =(
                SELECT
                    np_communication_id
                FROM
                    trophy_title
                WHERE
                    id = :game_id
            ) AND ttp.account_id = p.account_id
        )");
    $query->bindParam(":game_id", $gameId, PDO::PARAM_INT);
    $query->execute();

    if ($status == 1) {
        $statusText = "delisted";
    } elseif ($status == 3) {
        $statusText = "obsolete";
    } else {
        $statusText = "normal";
    }

    $success = "<p>Game ". $gameId ." is now set as ". $statusText .". All affected players will be updated soon, and ranks updated the next whole hour.</p>";
}

?>
<!doctype html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Admin ~ Game Status</title>
    </head>
    <body>
        <a href="/admin/">Back</a><br><br>
        <form method="post" autocomplete="off">
            Game ID:<br>
            <input type="number" name="game"><br>
            Status:<br>
            <select name="status">
                <option value="0">Normal</option>
                <option value="1">Delisted</option>
                <option value="3">Obsolete</option>
            </select><br><br>
            <input type="submit" value="Submit">
        </form>

        <?php
        if (isset($success)) {
            echo $success;
        }
        ?>
    </body>
</html>
