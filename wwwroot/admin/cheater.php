<?php
require_once("../init.php");

if (isset($_POST["player"])) {
    $onlineId = $_POST["player"];

    $database->beginTransaction();
    $query = $database->prepare("UPDATE player SET `status` = 1, rank_last_week = 0, rarity_rank_last_week = 0, rank_country_last_week = 0, rarity_rank_country_last_week = 0 WHERE online_id = :online_id");
    $query->bindValue(":online_id", $onlineId, PDO::PARAM_STR);
    $query->execute();
    $database->commit();

    $success = "<p>Player ". $onlineId ." is now tagged as a cheater.</p>";
}

?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Cheater</title>
    </head>
    <body>
        <div class="p-4">
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
        </div>
    </body>
</html>
