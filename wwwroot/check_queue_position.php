<?php

declare(strict_types=1);

require_once 'init.php';
require_once 'classes/PlayerQueueController.php';

$controller = PlayerQueueController::create($database);

$requestData = $_REQUEST ?? [];
$serverData = $_SERVER ?? [];

echo $controller->handleQueuePosition($requestData, $serverData);
