<?php
declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/GameStatusRequestHandler.php';
require_once '../classes/Admin/AdminLayoutRenderer.php';

$gameStatusService = new GameStatusService($database);
$requestHandler = new GameStatusRequestHandler($gameStatusService);

$request = AdminRequest::fromGlobals($_SERVER ?? [], $_POST ?? []);
$result = $requestHandler->handleRequest($request);
$success = $result->getSuccessMessage();
$error = $result->getErrorMessage();

$layoutRenderer = new AdminLayoutRenderer();

echo $layoutRenderer->render('Admin ~ Game Status', static function () use ($success, $error): void {
    ?>
    <form method="post" autocomplete="off">
        Game ID:<br>
        <input type="number" name="game"><br>
        Status:<br>
        <select name="status">
            <option value="0">Normal</option>
            <option value="1">Delisted</option>
            <option value="3">Obsolete</option>
            <option value="4">Delisted &amp; Obsolete</option>
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
