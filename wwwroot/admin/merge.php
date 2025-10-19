<?php

declare(strict_types=1);

require_once __DIR__ . '/../classes/ExecutionEnvironmentConfigurator.php';

ExecutionEnvironmentConfigurator::create()
    ->addIniSetting('max_execution_time', '0')
    ->addIniSetting('max_input_time', '0')
    ->addIniSetting('mysql.connect_timeout', '0')
    ->enableUnlimitedExecution()
    ->configure();

require_once '../init.php';
require_once '../classes/TrophyMergeService.php';
require_once '../classes/Admin/TrophyMergeRequestHandler.php';
require_once '../classes/Admin/AdminLayoutRenderer.php';

$mergeService = new TrophyMergeService($database);
$requestHandler = new TrophyMergeRequestHandler($mergeService);
$message = $requestHandler->handle($_POST ?? []);

$layoutRenderer = new AdminLayoutRenderer();

echo $layoutRenderer->render('Admin ~ Merge Games', static function () use ($message): void {
    ?>
    <form method="post" autocomplete="off">
        Game Child ID:<br>
        <input type="number" name="child"><br>
        Game Parent ID:<br>
        <input type="number" name="parent"><br>
        Method:<br>
        <select name="method">
            <option value="order">Order</option>
            <option value="name">Name</option>
            <option value="icon">Icon</option>
        </select><br><br>
        Trophy Child ID:<br>
        <input type="text" name="trophychild"><br>
        Trophy Parent ID:<br>
        <input type="number" name="trophyparent"><br><br>
        <input type="submit" value="Submit">
    </form>

    <?= $message; ?>
    <?php
});
