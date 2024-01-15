<?php
require_once("../init.php");

if (!empty($_GET["delete"])) {
    $reportId = $_GET["delete"];

    $sql = "DELETE FROM player_report WHERE report_id = :report_id";
    $query = $database->prepare($sql);
    $query->bindParam(":report_id", $reportId, PDO::PARAM_INT);
    $query->execute();
}
?>
<!doctype html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Admin ~ Reported Players</title>
    </head>
    <body>
        <a href="/admin/">Back</a><br><br>
        <?php
        $sql = "SELECT pr.report_id, p.online_id, pr.explanation FROM player_report pr JOIN player p USING (account_id)";
        $query = $database->prepare($sql);
        $query->execute();
        $reportedPlayers = $query->fetchAll();

        foreach ($reportedPlayers as $reportedPlayer) {
            echo "<a href='/player/". $reportedPlayer["online_id"] ."'>". $reportedPlayer["online_id"] ."</a><br>". nl2br(htmlentities($reportedPlayer["explanation"], ENT_QUOTES, 'UTF-8')) ."<br><a href='?delete=". $reportedPlayer["report_id"] ."'>Delete</a><hr>";
        }
        ?>
    </body>
</html>
