<?php
$maintenance = false;
if ($maintenance) {
    require_once 'maintenance.php';
    die();
}

require_once 'init.php';

$request = $applicationContainer->createRequestFromGlobals();
$application = $applicationContainer->createApplication($request);
$application->run();
