<?php
declare(strict_types=1);

ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
ini_set("default_socket_timeout", "6000");
set_time_limit(0);

require_once("../init.php");
require_once("../classes/Admin/TrophyStatusService.php");

$trophyStatusService = new TrophyStatusService($database);

$trophyInput = "";
$statusInput = "1";
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST["trophy"]) || isset($_POST["game"]))) {
    $status = isset($_POST["status"]) ? (int) $_POST["status"] : 1;
    $statusInput = (string) $status;

    try {
        if (!empty($_POST["game"])) {
            if (!ctype_digit((string) $_POST["game"])) {
                throw new InvalidArgumentException('Game ID must be numeric.');
            }

            $gameId = (int) $_POST["game"];
            $trophyIds = $trophyStatusService->getTrophyIdsForGame($gameId);
            $trophyInput = implode(',', array_map('strval', $trophyIds));
        } else {
            $trophyInput = (string) ($_POST["trophy"] ?? '');
            $trophyIds = $trophyStatusService->parseTrophyIds($trophyInput);
        }

        $result = $trophyStatusService->updateTrophies($trophyIds, $status);
        $success = $result->toHtml();
    } catch (Throwable $exception) {
        $success = '<p>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    }
} elseif (isset($_GET["trophy"])) {
    $trophyInput = (string) $_GET["trophy"];
    if (isset($_GET["status"])) {
        $statusInput = (string) $_GET["status"];
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
        <title>Admin ~ Unobtainable Trophy</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="post" autocomplete="off">
                Game ID:<br>
                <input type="text" name="game" /><br>
                Trophy ID:<br>
                <textarea name="trophy" rows="10" cols="30"><?= htmlspecialchars(str_replace(",", PHP_EOL, $trophyInput), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
                <br>
                Status:<br>
                <select name="status">
                    <option value="1" <?= ($statusInput == "1" ? "selected" : ""); ?>>Unobtainable</option>
                    <option value="0" <?= ($statusInput == "0" ? "selected" : ""); ?>>Obtainable</option>
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
