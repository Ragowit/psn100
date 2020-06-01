<?php
require_once("../init.php");

if (isset($_POST["player"])) {
    $onlineId = $_POST["player"];

    $database->beginTransaction();
    $query = $database->prepare("UPDATE player SET status = 1, `rank` = 0, rank_last_week = 0, rarity_rank = 0, rarity_rank_last_week = 0, rank_country = 0, rank_country_last_week = 0, rarity_rank_country = 0, rarity_rank_country_last_week = 0 WHERE online_id = :online_id");
    $query->bindParam(":online_id", $onlineId, PDO::PARAM_STR);
    $query->execute();
    $database->commit();

    $success = "<p>Player ". $onlineId ." is now tagged as a cheater. Stats will be recalculated next cron job.</p>";
}

?>
<!doctype html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Admin ~ Cheater</title>
    </head>
    <body>
        <a href="/admin/">Back</a><br><br>
        <form method="post" autocomplete="off">
            Player:<br>
            <input type="text" name="player"><br><br>
            <input type="submit" value="Submit">
        </form>

        <?php
        if (isset($success)) {
            echo $success;
        }
        ?>
    </body>
</html>
