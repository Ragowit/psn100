<?php
$maintenance = false;
if ($maintenance) {
    require_once 'maintenance.php';
    die();
}

require_once 'init.php';
require_once 'classes/Application.php';

$router = new Router($database);
$application = new Application($router, $_SERVER);
$application->run();
