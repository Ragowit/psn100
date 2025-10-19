<?php

declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/GameCopyService.php';
require_once '../classes/Admin/GameCopyHandler.php';
require_once '../classes/Admin/AdminLayoutRenderer.php';

$gameCopyService = new GameCopyService($database);
$gameCopyHandler = new GameCopyHandler($gameCopyService);
$message = $gameCopyHandler->handle($_POST);

$layoutRenderer = new AdminLayoutRenderer();

echo $layoutRenderer->render('Admin ~ Copy', static function () use ($message): void {
    ?>
    <form method="post" autocomplete="off">
        Game Child ID:<br>
        <input type="number" name="child"><br>
        Game Parent ID:<br>
        <input type="number" name="parent"><br>
        <br>
        <input type="submit" value="Submit">
    </form>

    <?= $message; ?>
    <?php
});
