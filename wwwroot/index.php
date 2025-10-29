<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/MaintenanceMode.php';
require_once __DIR__ . '/classes/MaintenanceResponder.php';

$defaultMaintenanceState = false;
$maintenanceMode = MaintenanceMode::fromEnvironment(
    $_SERVER ?? [],
    __DIR__ . '/maintenance.php',
    $defaultMaintenanceState
);

if ($maintenanceMode->isEnabled()) {
    $maintenanceResponder = new MaintenanceResponder();
    $maintenanceResponder->respond($maintenanceMode);
}

require_once __DIR__ . '/init.php';

$applicationRunner = $applicationBootstrapper->createApplicationRunner($maintenanceMode);
$applicationRunner->run();
