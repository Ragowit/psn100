<?php

declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/CheaterRequestHandler.php';
require_once '../classes/Admin/AdminLayoutRenderer.php';

$cheaterService = new CheaterService($database);
$requestHandler = new CheaterRequestHandler($cheaterService);

$request = AdminRequest::fromGlobals($_SERVER ?? [], $_POST ?? []);
$result = $requestHandler->handle($request);
$successMessage = $result->getSuccessMessage();
$errorMessage = $result->getErrorMessage();

$layoutRenderer = new AdminLayoutRenderer();

echo $layoutRenderer->render('Admin ~ Cheater', static function () use ($successMessage, $errorMessage): void {
    ?>
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
    <?php
});
