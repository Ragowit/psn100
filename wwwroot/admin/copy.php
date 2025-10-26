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
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
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
                <div class="form-check mt-3">
                    <input type="hidden" name="copy_icon_url" value="0">
                    <input class="form-check-input" type="checkbox" name="copy_icon_url" id="copy-icon-url" value="1" checked>
                    <label class="form-check-label" for="copy-icon-url">
                        Copy icon URL
                    </label>
                </div>
                <div class="form-check mt-2">
                    <input type="hidden" name="copy_set_version" value="0">
                    <input class="form-check-input" type="checkbox" name="copy_set_version" id="copy-set-version" value="1" checked>
                    <label class="form-check-label" for="copy-set-version">
                        Copy set version
                    </label>
                </div>
                <br>
                <input type="submit" value="Submit">
            </form>

            <?= $message; ?>
        </div>
    </body>
</html>
