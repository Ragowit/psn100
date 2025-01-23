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
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Reported Players</title>
    </head>
    <body>
        <div class="p-4">
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
        </div>
    </body>
</html>
