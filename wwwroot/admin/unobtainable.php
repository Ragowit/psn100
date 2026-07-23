<?php

declare(strict_types=1);

require_once __DIR__ . '/../classes/ExecutionEnvironmentConfigurator.php';

ExecutionEnvironmentConfigurator::create()
    ->addIniSetting('max_execution_time', '0')
    ->addIniSetting('mysql.connect_timeout', '0')
    ->addIniSetting('default_socket_timeout', '6000')
    ->enableUnlimitedExecution()
    ->configure();

require_once __DIR__ . '/bootstrap.php';
require_once("../classes/Admin/TrophyStatusInputParser.php");
require_once("../classes/Admin/TrophyStatusService.php");
require_once("../classes/Admin/TrophyStatusPage.php");
require_once("../classes/TrophyMetaStatus.php");

$trophyStatusInputParser = new TrophyStatusInputParser($database);
$trophyStatusService = new TrophyStatusService($database);
$trophyStatusPage = new TrophyStatusPage($trophyStatusInputParser, $trophyStatusService);

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$postData = $_POST ?? [];
$queryData = $_GET ?? [];

$pageResult = $trophyStatusPage->handleRequest($requestMethod, $postData, $queryData);
$trophyInput = $pageResult->getTrophyInput();
$statusInput = $pageResult->getStatusInput();
$status = TrophyMetaStatus::fromMixed($statusInput);

?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="<?= htmlspecialchars(BootstrapAssets::stylesheetUrl(), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
        <title>Admin ~ Unobtainable Trophy</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="post" autocomplete="off">
                    <?php AdminBootstrap::renderCsrfField(); ?>
                Game ID:<br>
                <input type="text" name="game" /><br>
                Trophy ID:<br>
                <textarea name="trophy" rows="10" cols="30"><?= htmlspecialchars(str_replace(",", PHP_EOL, $trophyInput), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
                <br>
                Status:<br>
                <select name="status">
                    <option value="<?= TrophyMetaStatus::Unobtainable->value ?>" <?= ($status === TrophyMetaStatus::Unobtainable ? "selected" : ""); ?>>Unobtainable</option>
                    <option value="<?= TrophyMetaStatus::Obtainable->value ?>" <?= ($status === TrophyMetaStatus::Obtainable ? "selected" : ""); ?>>Obtainable</option>
                </select><br><br>
                <input type="submit" value="Submit">
            </form>

            <?php
            if ($pageResult->hasMessage()) {
                echo $pageResult->getMessage();
            }
            ?>
        </div>
    </body>
</html>
