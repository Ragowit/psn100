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
        <form method="post">
            Game ID:<br>
            <input type="number" name="game"><br>
            Message:<br>
            <textarea name="message"></textarea><br><br>
            <input type="submit" value="Submit">
        </form>

        <?php
        if (isset($success)) {
            echo $success;
        }
        ?>
    </body>
</html>
