<?php

declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/CheaterService.php';

$cheaterService = new CheaterService($database);

$successMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $onlineId = isset($_POST['player']) ? trim((string) $_POST['player']) : '';

    try {
        $cheaterService->markPlayerAsCheater($onlineId);
        $successMessage = sprintf('<p>Player %s is now tagged as a cheater.</p>', htmlentities($onlineId));
    } catch (InvalidArgumentException $exception) {
        $errorMessage = sprintf('<p class="text-danger">%s</p>', htmlentities($exception->getMessage()));
    } catch (Throwable $exception) {
        $errorMessage = '<p class="text-danger">An unexpected error occurred while updating the player.</p>';
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
            if ($successMessage !== null) {
                echo $successMessage;
            }

            if ($errorMessage !== null) {
                echo $errorMessage;
            }
            ?>
        </div>
    </body>
</html>
