<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/MaintenancePageRenderer.php';

$page = MaintenancePage::createDefault();
$renderer = new MaintenancePageRenderer();

echo $renderer->render($page);
