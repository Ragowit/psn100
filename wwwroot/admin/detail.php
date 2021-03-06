<?php
require_once("../init.php");

if (isset($_POST["game"]) && ctype_digit(strval($_POST["game"]))) {
    $gameId = $_POST["game"];
    $name = $_POST["name"];
    $iconUrl = $_POST["icon_url"];
    $platform = $_POST["platform"];
    $message = $_POST["message"];
    $setVersion = $_POST["set_version"];

    $database->beginTransaction();
    $query = $database->prepare("UPDATE
            trophy_title
        SET
            name = :name,
            icon_url = :icon_url,
            platform = :platform,
            message = :message,
            set_version = :set_version
        WHERE
            id = :game_id");
    $query->bindParam(":name", $name, PDO::PARAM_STR);
    $query->bindParam(":icon_url", $iconUrl, PDO::PARAM_STR);
    $query->bindParam(":platform", $platform, PDO::PARAM_STR);
    $query->bindParam(":message", $message, PDO::PARAM_STR);
    $query->bindParam(":set_version", $setVersion, PDO::PARAM_STR);
    $query->bindParam(":game_id", $gameId, PDO::PARAM_INT);
    $query->execute();
    $database->commit();

    $query = $database->prepare("SELECT 
            name,
            icon_url,
            platform,
            message,
            set_version
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
            name,
            icon_url,
            platform,
            message,
            set_version
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
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Admin ~ Game Details</title>
    </head>
    <body>
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
                <input type="text" name="name" style="width: 859px;" value="<?= $trophyTitle["name"]; ?>" ><br>
                Icon URL:<br>
                <input type="text" name="icon_url" style="width: 859px;" value="<?= $trophyTitle["icon_url"]; ?>"><br>
                Platform:<br>
                <input type="text" name="platform" style="width: 859px;" value="<?= $trophyTitle["platform"]; ?>"><br>
                Set Version:<br>
                <input type="text" name="set_version" style="width: 859px;" value="<?= $trophyTitle["set_version"]; ?>"><br>
                Message:<br>
                <textarea name="message" rows="6" cols="120"><?= $trophyTitle["message"]; ?></textarea><br><br>
                <input type="submit" value="Submit">
            </form>

            <p>
                Standard messages:<br>
                <?= htmlentities("Unobtainable trophies (<a href=\"https://github.com/Ragowit/psn100/issues/\">source</a>).<br>"); ?><br>
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
    </body>
</html>
