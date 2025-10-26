<?php

declare(strict_types=1);

require_once '../init.php';
require_once '../classes/GameResetService.php';
require_once '../classes/Admin/GameResetRequestHandler.php';

$gameResetService = new GameResetService($database);
$requestHandler = new GameResetRequestHandler($gameResetService);

$request = AdminRequest::fromGlobals($_SERVER ?? [], $_POST ?? []);
$result = $requestHandler->handleRequest($request);
$success = $result->getSuccessMessage();
$error = $result->getErrorMessage();

?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
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
            if ($error !== null) {
                echo $error;
            }

            if ($success !== null) {
                echo $success;
            }
            ?>
        </div>
    </body>
</html>
