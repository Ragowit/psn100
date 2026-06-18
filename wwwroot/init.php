<?php

declare(strict_types=1);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/classes/ApplicationBootstrapper.php';

$applicationBootstrapper = ApplicationBootstrapper::bootstrap();
$applicationContainer = $applicationBootstrapper->getApplicationContainer();

$database = $applicationBootstrapper->getDatabase();
$utility = $applicationBootstrapper->getUtility();
$paginationRenderer = $applicationBootstrapper->getPaginationRenderer();
