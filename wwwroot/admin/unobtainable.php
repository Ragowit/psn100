<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/ExecutionEnvironmentConfigurator.php';

ExecutionEnvironmentConfigurator::create()
    ->addIniSetting('max_execution_time', '0')
    ->addIniSetting('mysql.connect_timeout', '0')
    ->addIniSetting('default_socket_timeout', '6000')
    ->enableUnlimitedExecution()
    ->configure();

require_once '../init.php';
require_once '../classes/Admin/TrophyStatusService.php';
require_once '../classes/Admin/TrophyStatusPage.php';
require_once '../classes/Admin/AdminLayoutRenderer.php';

$trophyStatusService = new TrophyStatusService($database);
$trophyStatusPage = new TrophyStatusPage($trophyStatusService);

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$postData = $_POST ?? [];
$queryData = $_GET ?? [];

$pageResult = $trophyStatusPage->handleRequest($requestMethod, $postData, $queryData);
$trophyInput = $pageResult->getTrophyInput();
$statusInput = $pageResult->getStatusInput();

$layoutRenderer = new AdminLayoutRenderer();

echo $layoutRenderer->render('Admin ~ Unobtainable Trophy', static function () use ($pageResult, $trophyInput, $statusInput): void {
    ?>
    <form method="post" autocomplete="off">
        Game ID:<br>
        <input type="text" name="game" /><br>
        Trophy ID:<br>
        <textarea name="trophy" rows="10" cols="30"><?= htmlspecialchars(str_replace(',', PHP_EOL, $trophyInput), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
        <br>
        Status:<br>
        <select name="status">
            <option value="1" <?= ($statusInput == "1" ? "selected" : ""); ?>>Unobtainable</option>
            <option value="0" <?= ($statusInput == "0" ? "selected" : ""); ?>>Obtainable</option>
        </select><br><br>
        <input type="submit" value="Submit">
    </form>

    <?php
    if ($pageResult->hasMessage()) {
        echo $pageResult->getMessage();
    }
    ?>
    <?php
});
