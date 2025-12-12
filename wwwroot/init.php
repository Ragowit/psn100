<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/ApplicationBootstrapper.php';

$applicationBootstrapper = ApplicationBootstrapper::bootstrap();
$applicationContainer = $applicationBootstrapper->getApplicationContainer();

$database = $applicationBootstrapper->getDatabase();
$utility = $applicationBootstrapper->getUtility();
$paginationRenderer = $applicationBootstrapper->getPaginationRenderer();
