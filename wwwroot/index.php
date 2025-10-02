<?php
$maintenance = false;
if ($maintenance) {
    require_once 'maintenance.php';
    die();
}

require_once 'init.php';
require_once 'classes/Application.php';
require_once 'classes/HttpRequest.php';

$gameRepository = new GameRepository($database);
$trophyRepository = new TrophyRepository($database);
$playerRepository = new PlayerRepository($database);

$router = new Router($gameRepository, $trophyRepository, $playerRepository);
$request = HttpRequest::fromGlobals();
$application = new Application($router, $request);
$application->run();
