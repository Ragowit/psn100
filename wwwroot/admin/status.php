<?php
require_once("../init.php");

if (isset($_POST["game"]) && ctype_digit(strval($_POST["game"]))) {
    $gameId = $_POST["game"];
    $status = $_POST["status"];

    $database->beginTransaction();
    $query = $database->prepare("UPDATE trophy_title SET status = :status WHERE id = :game_id");
    $query->bindValue(":status", $status, PDO::PARAM_INT);
    $query->bindValue(":game_id", $gameId, PDO::PARAM_INT);
    $query->execute();
    $database->commit();

    if ($status == 1) {
        $query = $database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_DELISTED', :param_1)");
        $query->bindValue(":param_1", $gameId, PDO::PARAM_INT);
        $query->execute();

        $statusText = "delisted";
    } elseif ($status == 3) {
        $query = $database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_OBSOLETE', :param_1)");
        $query->bindValue(":param_1", $gameId, PDO::PARAM_INT);
        $query->execute();

        $statusText = "obsolete";
    } elseif ($status == 4) {
        $query = $database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_DELISTED_AND_OBSOLETE', :param_1)");
        $query->bindValue(":param_1", $gameId, PDO::PARAM_INT);
        $query->execute();

        $statusText = "delisted &amp; obsolete";
    } else {
        $query = $database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_NORMAL', :param_1)");
        $query->bindValue(":param_1", $gameId, PDO::PARAM_INT);
        $query->execute();

        $statusText = "normal";
    }

    $success = "<p>Game ". $gameId ." is now set as ". $statusText .". All affected players will be updated soon, and ranks updated the next whole hour.</p>";
}

?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Game Status</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="post" autocomplete="off">
                Game ID:<br>
                <input type="number" name="game"><br>
                Status:<br>
                <select name="status">
                    <option value="0">Normal</option>
                    <option value="1">Delisted</option>
                    <option value="3">Obsolete</option>
                    <option value="4">Delisted &amp; Obsolete</option>
                </select><br><br>
                <input type="submit" value="Submit">
            </form>

            <?php
            if (isset($success)) {
                echo $success;
            }
            ?>
        </div>
    </body>
</html>
