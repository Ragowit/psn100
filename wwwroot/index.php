<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/MaintenanceMode.php';

$defaultMaintenanceState = false;
$maintenanceMode = MaintenanceMode::fromEnvironment(
    $_SERVER ?? [],
    __DIR__ . '/maintenance.php',
    $defaultMaintenanceState
);

if ($maintenanceMode->isEnabled()) {
    require_once $maintenanceMode->getTemplatePath();

    exit();
}

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/classes/ApplicationRunner.php';

$applicationRunner = ApplicationRunner::create($applicationContainer, $maintenanceMode);
$applicationRunner->run();
