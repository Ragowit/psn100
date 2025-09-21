<?php
require_once("../vendor/autoload.php");
require_once("../init.php");
require_once("../classes/TrophyCalculator.php");
require_once("../classes/Admin/GameRescanService.php");

$trophyCalculator = new TrophyCalculator($database);
$gameRescanService = new GameRescanService($database, $trophyCalculator);
$success = null;

if (isset($_POST["game"])) {
    if (!ctype_digit((string) $_POST["game"])) {
        $success = 'Please provide a valid game id.';
    } else {
        $gameId = (int) $_POST["game"];

        try {
            $message = $gameRescanService->rescan($gameId);
            $success = "<p>{$message}</p>";
        } catch (RuntimeException $exception) {
            $success = $exception->getMessage();
        }
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
        <title>Admin ~ Rescan Game</title>
    </head>
    <body>
        <div class="p-4">
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
        </div>
    </body>
</html>
