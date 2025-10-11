<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/ExecutionEnvironmentConfigurator.php';

ExecutionEnvironmentConfigurator::create()
    ->addIniSetting('max_execution_time', '0')
    ->addIniSetting('max_input_time', '0')
    ->addIniSetting('mysql.connect_timeout', '0')
    ->enableUnlimitedExecution()
    ->configure();

require_once("../init.php");
require_once("../classes/TrophyMergeService.php");
require_once("../classes/Admin/TrophyMergeRequestHandler.php");

$mergeService = new TrophyMergeService($database);
$requestHandler = new TrophyMergeRequestHandler($mergeService);
$message = $requestHandler->handle($_POST ?? []);
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Merge Games</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
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
        </div>
    </body>
</html>
