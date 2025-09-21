<?php
require_once("../init.php");
require_once("../classes/GameResetService.php");

$gameResetService = new GameResetService($database);
$success = null;

if (isset($_POST["game"]) && ctype_digit((string) $_POST["game"])) {
    $gameId = (int) $_POST["game"];
    $status = isset($_POST["status"]) ? (int) $_POST["status"] : 0;

    try {
        $message = $gameResetService->process($gameId, $status);
        $success = "<p>{$message}</p>";
    } catch (InvalidArgumentException $exception) {
        $success = $exception->getMessage();
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
            if ($success !== null) {
                echo $success;
            }
            ?>
        </div>
    </body>
</html>
