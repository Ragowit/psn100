<?php

declare(strict_types=1);

require_once '../vendor/autoload.php';
require_once '../init.php';
require_once '../classes/TrophyCalculator.php';
require_once '../classes/Admin/GameRescanService.php';
require_once '../classes/Admin/GameRescanRequestHandler.php';

$trophyCalculator = new TrophyCalculator($database);
$gameRescanService = new GameRescanService($database, $trophyCalculator);
$requestHandler = new GameRescanRequestHandler($gameRescanService);

$requestHandler->handleRequest($_POST ?? [], $_SERVER ?? []);
