<?php
$maintenance = false;
if ($maintenance) {
    require_once("maintenance.php");
    die();
}

require_once("init.php");
require_once("classes/Router.php");

// SCRIPT_URL isn't available in all web server configurations (for example,
// the PHP built-in development server). Fall back to REQUEST_URI and strip any
// query string so routing works everywhere without PHP notices.
$requestUri = $_SERVER["SCRIPT_URL"] ?? ($_SERVER["REQUEST_URI"] ?? "/");

$router = new Router($database);
$routeResult = $router->dispatch($requestUri);

if ($routeResult->shouldRedirect()) {
    $statusCode = $routeResult->getStatusCode() ?? 303;
    header("Location: " . $routeResult->getRedirect(), true, $statusCode);
    exit();
}

if ($routeResult->isNotFound()) {
    header('HTTP/1.1 404 Not Found');
    require_once("404.php");
    exit();
}

if ($routeResult->shouldInclude()) {
    $variables = $routeResult->getVariables();
    if (!empty($variables)) {
        extract($variables, EXTR_SKIP);
    }

    require_once($routeResult->getInclude());
    return;
}
