<?php

declare(strict_types=1);

require_once '../init.php';
require_once '../classes/GameResetService.php';
require_once '../classes/Admin/GameResetRequestHandler.php';
require_once '../classes/Admin/AdminLayoutRenderer.php';

$gameResetService = new GameResetService($database);
$requestHandler = new GameResetRequestHandler($gameResetService);

$request = AdminRequest::fromGlobals($_SERVER ?? [], $_POST ?? []);
$result = $requestHandler->handleRequest($request);
$success = $result->getSuccessMessage();
$error = $result->getErrorMessage();

$layoutRenderer = new AdminLayoutRenderer();

echo $layoutRenderer->render('Admin ~ Reset / Delete', static function () use ($success, $error): void {
    ?>
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
    <?php
});
