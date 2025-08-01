<?php
require_once("../init.php");

if (isset($_POST["game"]) && ctype_digit(strval($_POST["game"]))) {
    $gameId = $_POST["game"];
    $status = $_POST["status"];

    // Safety check
    $query = $database->prepare("SELECT np_communication_id FROM trophy_title WHERE id = :game_id");
    $query->bindValue(":game_id", $gameId, PDO::PARAM_INT);
    $query->execute();
    $npCommunicationId = $query->fetchColumn();
    
    if (str_starts_with($npCommunicationId, "MERGE")) {
        if ($status == 0) { // Reset
            $database->beginTransaction();
            $query = $database->prepare("DELETE FROM trophy_merge WHERE parent_np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();
            
            $query = $database->prepare("DELETE FROM trophy_earned WHERE np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("DELETE FROM trophy_group_player WHERE np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("DELETE FROM trophy_title_player WHERE np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("UPDATE trophy_title SET owners = 0, owners_completed = 0 WHERE np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("UPDATE trophy_title SET parent_np_communication_id = NULL WHERE parent_np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();            
            $database->commit();

            $query = $database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_RESET', :param_1)");
            $query->bindValue(":param_1", $gameId, PDO::PARAM_INT);
            $query->execute();

            $success = "<p>Game ". $gameId ." was reset.</p>";
        } elseif ($status == 1) { // Delete
            $query = $database->prepare("SELECT `name` FROM trophy_title WHERE id = :game_id");
            $query->bindValue(":game_id", $gameId, PDO::PARAM_INT);
            $query->execute();
            $gameName = $query->fetchColumn();

            $database->beginTransaction();
            $query = $database->prepare("DELETE FROM trophy_merge WHERE parent_np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();
            
            $query = $database->prepare("DELETE FROM trophy WHERE np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("DELETE FROM trophy_earned WHERE np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("DELETE FROM trophy_group_player WHERE np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("DELETE FROM trophy_title_player WHERE np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("DELETE FROM trophy_group WHERE np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("DELETE FROM trophy_title WHERE np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("UPDATE trophy_title SET parent_np_communication_id = NULL WHERE parent_np_communication_id = :np_communication_id");
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->execute();
            $database->commit();

            $query = $database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`, `extra`) VALUES ('GAME_DELETE', :param_1, :extra)");
            $query->bindValue(":param_1", $gameId, PDO::PARAM_INT);
            $query->bindValue(":extra", $gameName, PDO::PARAM_STR);
            $query->execute();

            $success = "<p>Game ". $gameId ." was deleted.</p>";
        } else {
            $success = "Unknown method.";
        }
    } else {
        $success = "Can only reset/delete merged game entries.";
    }
}

?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Reset / Delete</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="post" autocomplete="off">
                Game ID:<br>
                <input type="number" name="game"><br>
                Reset or Delete:<br>
                <select name="status">
                    <option value="0">Reset</option>
                    <option value="1">Delete</option>
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
