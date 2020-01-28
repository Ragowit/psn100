<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("../vendor/autoload.php");
require_once("../init.php");

use PlayStation\Client;

if (isset($_POST["game"])) {
    $gameId = $_POST["game"];
    $query = $database->prepare("SELECT np_communication_id FROM trophy_title WHERE id = :id");
    $query->bindParam(":id", $gameId, PDO::PARAM_INT);
    $query->execute();
    $gameNCI = $query->fetchColumn();

    // Get current tokens
    $query = $database->prepare("SELECT * FROM setting");
    $query->execute();
    $workers = $query->fetchAll();

    $clients = array();

    // Login with all the tokens
    $database->beginTransaction();
    foreach ($workers as $worker) {
        $client = new Client();
        $refreshToken = $worker["refresh_token"];
        $client->login($refreshToken);

        // Store new token
        $refreshToken = $client->refreshToken();
        $query = $database->prepare("UPDATE setting SET refresh_token = :refresh_token WHERE id = :id");
        $query->bindParam(":refresh_token", $refreshToken, PDO::PARAM_STR);
        $query->bindParam(":id", $worker["id"], PDO::PARAM_INT);
        $query->execute();

        array_push($clients, $client);
    }
    $database->commit();

    $users = array();
    foreach ($clients as $client) {
        $user = $client->user("Ragowit");
        array_push($users, $user);
    }
    $client = 0;

    // // Update trophy_title
    // $game = $clients[0]->game($gameNCI);
    // $trophyTitleIconUrl = $game->trophyTitleIconUrl;
    // $trophyTitleIconFilename = md5_file($trophyTitleIconUrl) . strtolower(substr($trophyTitleIconUrl, strrpos($trophyTitleIconUrl, ".")));
    // // Download the title icon if we don't have it
    // if (!file_exists("../img/title/". $trophyTitleIconFilename)) {
    //     file_put_contents("../img/title/". $trophyTitleIconFilename, fopen($trophyTitleIconUrl, "r"));
    // }
    // $query = $database->prepare("UPDATE trophy_title SET name = :name, detail = :detail, icon_url = :icon_url, platform = :platform WHERE np_communication_id = :np_communication_id");
    // $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
    // $query->bindParam(":name", $game->trophyTitleName, PDO::PARAM_STR);
    // $query->bindParam(":detail", $game->trophyTitleDetail, PDO::PARAM_STR);
    // $query->bindParam(":icon_url", $trophyTitleIconFilename, PDO::PARAM_STR);
    // $query->bindParam(":platform", $game->trophyTitlePlatfrom, PDO::PARAM_STR);
    // $query->execute();

    $trophyGroups = $users[$client]->trophyGroups($gameNCI)->trophyGroups;
    $client++;
    if ($client >= count($clients)) {
        $client = 0;
    }
    foreach ($trophyGroups as $trophyGroup) {
        // Update trophy group (game + dlcs)
        $trophyGroupIconUrl = $trophyGroup->trophyGroupIconUrl;
        $trophyGroupIconFilename = md5_file($trophyGroupIconUrl) . strtolower(substr($trophyGroupIconUrl, strrpos($trophyGroupIconUrl, ".")));
        // Download the group icon if we don't have it
        if (!file_exists("../img/group/". $trophyGroupIconFilename)) {
            file_put_contents("../img/group/". $trophyGroupIconFilename, fopen($trophyGroupIconUrl, "r"));
        }

        $query = $database->prepare("UPDATE trophy_group SET name = :name, detail = :detail, icon_url = :icon_url WHERE np_communication_id = :np_communication_id AND group_id = :group_id");
        $query->bindParam(":np_communication_id", $gameNCI, PDO::PARAM_STR);
        $query->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
        $query->bindParam(":name", $trophyGroup->trophyGroupName, PDO::PARAM_STR);
        $query->bindParam(":detail", $trophyGroup->trophyGroupDetail, PDO::PARAM_STR);
        $query->bindParam(":icon_url", $trophyGroupIconFilename, PDO::PARAM_STR);
        // Don't update platinum/gold/silver/bronze here since our site recalculate this.
        $query->execute();

        $result = $users[$client]->trophies($gameNCI, $trophyGroup->trophyGroupId);
        $client++;
        if ($client >= count($clients)) {
            $client = 0;
        }
        foreach ($result as $trophies) {
            foreach ($trophies as $trophy) {
                // Update trophy
                $trophyIconUrl = $trophy->trophyIconUrl;
                $trophyIconFilename = md5_file($trophyIconUrl) . strtolower(substr($trophyIconUrl, strrpos($trophyIconUrl, ".")));
                // Download the trophy icon if we don't have it
                if (!file_exists("../img/trophy/". $trophyIconFilename)) {
                    file_put_contents("../img/trophy/". $trophyIconFilename, fopen($trophyIconUrl, "r"));
                }

                $query = $database->prepare("UPDATE trophy
                    SET hidden = :hidden, type = :type, name = :name, detail = :detail, icon_url = :icon_url, rare = :rare, earned_rate = :earned_rate
                    WHERE np_communication_id = :np_communication_id AND group_id = :group_id AND order_id = :order_id");
                $query->bindParam(":np_communication_id", $gameNCI, PDO::PARAM_STR);
                $query->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
                $query->bindParam(":order_id", $trophy->trophyId, PDO::PARAM_INT);
                $query->bindParam(":hidden", $trophy->trophyHidden, PDO::PARAM_INT);
                $query->bindParam(":type", $trophy->trophyType, PDO::PARAM_STR);
                $query->bindParam(":name", $trophy->trophyName, PDO::PARAM_STR);
                $query->bindParam(":detail", $trophy->trophyDetail, PDO::PARAM_STR);
                $query->bindParam(":icon_url", $trophyIconFilename, PDO::PARAM_STR);
                $query->bindParam(":rare", $trophy->trophyRare, PDO::PARAM_INT);
                $query->bindParam(":earned_rate", $trophy->trophyEarnedRate, PDO::PARAM_STR);
                $query->execute();
            }
        }
    }

    $success = "<p>Game ". $gameId ." have been rescanned.</p>";
}

?>
<!doctype html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Admin ~ Rescan Game</title>
    </head>
    <body>
        <a href="/admin/">Back</a><br><br>
        <form method="post" autocomplete="off">
            Game:<br>
            <input type="text" name="game"><br><br>
            <input type="submit" value="Submit">
        </form>

        <?php
        if (isset($success)) {
            echo $success;
        }
        ?>
    </body>
</html>
