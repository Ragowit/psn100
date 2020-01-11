<?php
require_once("../init.php");

if (ctype_digit(strval($_POST["game"]))) {
    $gameId = $_POST["game"];
    $message = $_POST["message"];

    $database->beginTransaction();
    $query = $database->prepare("UPDATE trophy_title SET message = :message WHERE id = :game_id");
    $query->bindParam(":message", $message, PDO::PARAM_STR);
    $query->bindParam(":game_id", $gameId, PDO::PARAM_INT);
    $query->execute();
    $database->commit();

    $success = "<p>Game ID ". $gameId ." now have the message: ". $message ."</p>";
}

?>
<!doctype html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Admin ~ Game Message</title>
    </head>
    <body>
        <a href="/admin/">Back</a><br><br>
        <form method="post">
            Game ID:<br>
            <input type="number" name="game"><br>
            Message:<br>
            <textarea name="message" rows="4" cols="50"></textarea><br><br>
            <input type="submit" value="Submit">
        </form>

        <p>
            Standard messages:<br>
            <?= htmlentities("This game have unobtainable trophies (<a href=\"https://github.com/Ragowit/psn100/issues/\">source</a>)."); ?><br>
            <?= htmlentities("This game is delisted (<a href=\"https://github.com/Ragowit/psn100/issues/\">source</a>). No trophies will be accounted for on any leaderboard."); ?><br>
        </p>

        <?php
        if (isset($success)) {
            echo $success;
        }
        ?>
    </body>
</html>
