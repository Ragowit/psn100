<?php
require_once("../init.php");

if (isset($_POST["game"]) && ctype_digit(strval($_POST["game"]))) {
    $gameId = $_POST["game"];
    $name = $_POST["name"];
    $iconUrl = $_POST["icon_url"];
    $platform = $_POST["platform"];
    $message = $_POST["message"];
    $setVersion = $_POST["set_version"];
    $region = (empty($_POST["region"]) ? null : $_POST["region"]);
    $psnprofilesId = (empty($_POST["psnprofiles_id"]) ? null : $_POST["psnprofiles_id"]);

    $database->beginTransaction();
    $query = $database->prepare("UPDATE
            trophy_title
        SET
            name = :name,
            icon_url = :icon_url,
            platform = :platform,
            message = :message,
            set_version = :set_version,
            region = :region,
            psnprofiles_id = :psnprofiles_id
        WHERE
            id = :game_id");
    $query->bindParam(":name", $name, PDO::PARAM_STR);
    $query->bindParam(":icon_url", $iconUrl, PDO::PARAM_STR);
    $query->bindParam(":platform", $platform, PDO::PARAM_STR);
    $query->bindParam(":message", $message, PDO::PARAM_STR);
    $query->bindParam(":set_version", $setVersion, PDO::PARAM_STR);
    $query->bindParam(":region", $region, PDO::PARAM_STR);
    $query->bindParam(":psnprofiles_id", $psnprofilesId, PDO::PARAM_STR);
    $query->bindParam(":game_id", $gameId, PDO::PARAM_INT);
    $query->execute();
    $database->commit();

    $query = $database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_UPDATE', :param_1)");
    $query->bindParam(":param_1", $gameId, PDO::PARAM_INT);
    $query->execute();

    $query = $database->prepare("SELECT 
            np_communication_id,
            name,
            icon_url,
            platform,
            message,
            set_version,
            region,
            psnprofiles_id
        FROM
            trophy_title
        WHERE
            id = :game_id");
    $query->bindParam(":game_id", $gameId, PDO::PARAM_INT);
    $query->execute();
    $trophyTitle = $query->fetch();

    $success = "<p>Game ID ". $gameId ." is updated.</p>";
} elseif (isset($_GET["game"]) && ctype_digit(strval($_GET["game"]))) {
    $gameId = $_GET["game"];

    $query = $database->prepare("SELECT 
            np_communication_id,
            name,
            icon_url,
            platform,
            message,
            set_version,
            region,
            psnprofiles_id
        FROM
            trophy_title
        WHERE
            id = :game_id");
    $query->bindParam(":game_id", $gameId, PDO::PARAM_INT);
    $query->execute();
    $trophyTitle = $query->fetch();
}

?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Game Details</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="get" autocomplete="off">
                Game ID:<br>
                <input type="number" name="game"><br>
                <input type="submit" value="Fetch">
            </form>

            <?php
            if (isset($trophyTitle)) {
                ?>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="game" value="<?= $gameId; ?>"><br>
                    Name:<br>
                    <input type="text" name="name" style="width: 859px;" value="<?= htmlentities($trophyTitle["name"]); ?>" ><br>
                    Icon URL:<br>
                    <input type="text" name="icon_url" style="width: 859px;" value="<?= $trophyTitle["icon_url"]; ?>"><br>
                    Platform:<br>
                    <input type="text" name="platform" style="width: 859px;" value="<?= $trophyTitle["platform"]; ?>"><br>
                    Set Version:<br>
                    <input type="text" name="set_version" style="width: 859px;" value="<?= $trophyTitle["set_version"]; ?>"><br>
                    Region:<br>
                    <input type="text" name="region" style="width: 859px;" value="<?= $trophyTitle["region"]; ?>"><br>
                    NP Communication ID:<br>
                    <input type="text" name="np_communication_id" style="width: 859px;" value="<?= $trophyTitle["np_communication_id"]; ?>" readonly><br>
                    PSNProfiles ID:<br>
                    <input type="text" name="psnprofiles_id" style="width: 859px;" value="<?= $trophyTitle["psnprofiles_id"]; ?>"><br>
                    Message:<br>
                    <textarea name="message" rows="6" cols="120"><?= $trophyTitle["message"]; ?></textarea><br><br>
                    <input type="submit" value="Submit">
                </form>

                <p>
                    Standard messages:<br>
                    <?= htmlentities("This game is delisted (<a href=\"https://github.com/Ragowit/psn100/issues/\">source</a>). No trophies will be accounted for on any leaderboard."); ?><br>
                    <?= htmlentities("This game is obsolete, no trophies will be accounted for on any leaderboard. Please play <a href=\"/game/\"></a> instead."); ?><br>
                </p>
                <?php
            }
            ?>

            <?php
            if (isset($success)) {
                echo $success;
            }
            ?>
        </div>
    </body>
</html>
