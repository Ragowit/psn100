<?php
$maintenance = false;
if ($maintenance) {
    require_once 'maintenance.php';
    die();
}

require_once 'init.php';
require_once 'classes/Application.php';
require_once 'classes/HttpRequest.php';

$router = new Router($database);
$request = HttpRequest::fromGlobals();
$application = new Application($router, $request);
$application->run();
