<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
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
        <link href="<?= Html::escape(BootstrapAssets::stylesheetUrl()); ?>" rel="stylesheet">
        <title>Admin ~ Reset / Delete</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="post" autocomplete="off">
                    <?php AdminBootstrap::renderCsrfField(); ?>
                Game ID:<br>
                <input type="number" name="game"><br>
                Reset or Delete:<br>
                <select name="status">
                    <option value="<?= Html::escape((string) GameResetAction::RESET->value); ?>">Reset</option>
                    <option value="<?= Html::escape((string) GameResetAction::DELETE->value); ?>">Delete</option>
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
