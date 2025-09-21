<?php

declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/GameCopyService.php';
require_once '../classes/Admin/GameCopyHandler.php';

$gameCopyService = new GameCopyService($database);
$gameCopyHandler = new GameCopyHandler($gameCopyService);
$message = $gameCopyHandler->handle($_POST);
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Copy</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="post" autocomplete="off">
                Game Child ID:<br>
                <input type="number" name="child"><br>
                Game Parent ID:<br>
                <input type="number" name="parent"><br>
                <br>
                <input type="submit" value="Submit">
            </form>

            <?= $message; ?>
        </div>
    </body>
</html>
